<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, dependency-free spreadsheet reader.
 *
 * Reads the first worksheet of an .xlsx file (Office Open XML — a zip of XML
 * parts) or a .csv file into a list of rows, where each row is a 0-indexed
 * array of cell strings. This exists so the enrollment-records upload can parse
 * Excel exports without pulling in phpoffice/phpspreadsheet (the project's
 * advisory-locked Laravel version blocks new composer requires).
 *
 * Legacy binary .xls is NOT supported — callers should reject it and ask the
 * admin to save as .xlsx or CSV.
 */
class SpreadsheetReader
{
    /**
     * @return array<int, array<int, string>> list of rows (each a 0-indexed array of strings)
     */
    public static function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xlsx' => self::readXlsx($path),
            'csv', 'txt' => self::readCsv($path),
            default => throw new RuntimeException("Unsupported file type: .{$extension}. Please upload a .xlsx or .csv file."),
        };
    }

    private static function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // fgetcsv yields [null] for a blank line.
                if ($data === [null]) {
                    continue;
                }
                $rows[] = array_map(fn ($v) => (string) ($v ?? ''), $data);
            }
            fclose($handle);
        }

        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the uploaded .xlsx file.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));
            if ($sheetXml === false) {
                throw new RuntimeException('The .xlsx file has no readable worksheet.');
            }

            $ws = new SimpleXMLElement($sheetXml);

            $rows = [];
            foreach ($ws->sheetData->row as $row) {
                $cells = [];
                $maxIdx = -1;

                foreach ($row->c as $c) {
                    $col = self::columnIndex((string) $c['r']);
                    $type = (string) $c['t'];

                    if ($type === 's') {
                        $value = $shared[(int) $c->v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = (string) $c->is->t;
                    } else {
                        $value = (string) $c->v;
                    }

                    $cells[$col] = $value;
                    $maxIdx = max($maxIdx, $col);
                }

                // Fill gaps so downstream indexed access is contiguous.
                $normalized = [];
                for ($i = 0; $i <= $maxIdx; $i++) {
                    $normalized[$i] = $cells[$i] ?? '';
                }
                $rows[] = $normalized;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * Read xl/sharedStrings.xml into an ordered array of strings.
     *
     * @return array<int, string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ((new SimpleXMLElement($xml))->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            } else {
                // Rich-text runs: concatenate each run's text.
                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $strings[] = $text;
            }
        }

        return $strings;
    }

    /**
     * Resolve the path of the first worksheet from the workbook relationships,
     * falling back to the conventional xl/worksheets/sheet1.xml.
     */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            try {
                $wb = new SimpleXMLElement($workbook);
                $sheet = $wb->sheets->sheet[0] ?? null;
                if ($sheet !== null) {
                    $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                    foreach ((new SimpleXMLElement($rels))->Relationship as $rel) {
                        if ((string) $rel['Id'] === $rid) {
                            return 'xl/'.ltrim((string) $rel['Target'], '/');
                        }
                    }
                }
            } catch (\Throwable) {
                // Fall through to the conventional path.
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * Convert an A1-style cell reference to a 0-based column index
     * ("A" → 0, "B" → 1, "AA" → 26).
     */
    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $ref);
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}

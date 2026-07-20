<?php

namespace App\Support;

use Generator;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * Minimal, dependency-free spreadsheet reader.
 *
 * Reads the first worksheet of an .xlsx file (Office Open XML — a zip of XML
 * parts) or a .csv file as rows, where each row is a 0-indexed array of cell
 * strings. This exists so the enrollment-records upload can parse Excel
 * exports without pulling in phpoffice/phpspreadsheet (the project's
 * advisory-locked Laravel version blocks new composer requires).
 *
 * Prefer {@see self::stream()} — it yields one row at a time and holds no more
 * than a single row in memory, which is what makes 50-100MB enrolment CSVs
 * importable at all. {@see self::rows()} materialises the whole file and is
 * only safe for small ones.
 *
 * CSV streams with no practical size ceiling. XLSX does not: cells reference
 * xl/sharedStrings.xml by index, so that part has to be resident, and callers
 * cap .xlsx uploads accordingly (see EnrollmentRecordController::MAX_XLSX_KB).
 *
 * Legacy binary .xls is NOT supported — callers should reject it and ask the
 * admin to save as .xlsx or CSV.
 */
class SpreadsheetReader
{
    /**
     * Yield rows one at a time, holding only the current row in memory.
     *
     * @return Generator<int, array<int, string>>
     */
    public static function stream(string $path, string $extension): Generator
    {
        return match (strtolower($extension)) {
            'xlsx' => self::streamXlsx($path),
            'csv', 'txt' => self::streamCsv($path),
            default => throw new RuntimeException("Unsupported file type: .{$extension}. Please upload a .xlsx or .csv file."),
        };
    }

    /**
     * Materialise every row. Only safe for files known to be small — use
     * {@see self::stream()} for anything admin-uploaded.
     *
     * @return array<int, array<int, string>> list of rows (each a 0-indexed array of strings)
     */
    public static function rows(string $path, string $extension): array
    {
        return iterator_to_array(self::stream($path, $extension), false);
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    private static function streamCsv(string $path): Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not open the uploaded file.');
        }

        try {
            // Excel writes a UTF-8 BOM; left in place it corrupts the first
            // cell of the first row — which is the ticket ID we match on.
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // fgetcsv yields [null] for a blank line.
                if ($data === [null]) {
                    continue;
                }

                yield array_map(fn ($v) => (string) ($v ?? ''), $data);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Walk the worksheet with XMLReader rather than building a DOM over it.
     * A 20MB .xlsx decompresses to hundreds of MB of XML, which SimpleXMLElement
     * cannot hold within a normal memory_limit.
     *
     * @return Generator<int, array<int, string>>
     */
    private static function streamXlsx(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the uploaded .xlsx file. It may be corrupt or not a real .xlsx file.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetPath = self::firstSheetPath($zip);

            if ($zip->locateName($sheetPath) === false) {
                throw new RuntimeException('The .xlsx file has no readable worksheet.');
            }
        } finally {
            $zip->close();
        }

        $reader = new XMLReader;
        // The zip:// wrapper decompresses on demand, so the sheet XML is never
        // fully resident.
        if (! @$reader->open('zip://'.$path.'#'.$sheetPath)) {
            throw new RuntimeException('The .xlsx worksheet could not be read.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $rowXml = $reader->readOuterXml();
                if ($rowXml === '') {
                    continue;
                }

                // One <row> at a time is small enough for SimpleXMLElement.
                yield self::parseRow(new SimpleXMLElement($rowXml), $shared);

                // Don't descend into the row we just consumed.
                $reader->next();
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, string>
     */
    private static function parseRow(SimpleXMLElement $row, array $shared): array
    {
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

        return $normalized;
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

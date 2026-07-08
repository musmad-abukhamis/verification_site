<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Record;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Enrollment records upload — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/enrollment_records + its
 * /api/upload/bvnenrolments handler: the admin uploads a spreadsheet of BVN
 * enrolment rows which are upserted (by ticket_id) into the `Record` table.
 *
 * The source used the SheetJS `xlsx` reader; here we parse .xlsx/.csv natively
 * (see App\Support\SpreadsheetReader) to avoid adding a composer dependency.
 * Columns are positional, matching the source's 0..18 mapping.
 */
class EnrollmentRecordController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/EnrollmentRecords/Index');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20MB
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            $hint = $ext === 'xls'
                ? ' Legacy .xls is not supported — please re-save the file as .xlsx or CSV.'
                : '';

            return back()->withErrors(['file' => 'Unsupported file type.'.$hint]);
        }

        try {
            $rows = SpreadsheetReader::rows($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            Log::error('Enrolment upload parse error: '.$e->getMessage());

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // Positional column mapping (mirrors the source's r[0]..r[18]).
        $records = [];
        foreach ($rows as $r) {
            $ticketId = self::clean($r[0] ?? '');
            if ($ticketId === '') {
                continue;
            }

            $records[] = [
                'ticket_id' => $ticketId,
                'bvn' => self::clean($r[1] ?? ''),
                'org_name' => self::clean($r[2] ?? ''),
                'org_id' => self::clean($r[3] ?? ''),
                'enrollee_name' => self::clean($r[4] ?? ''),
                'enroller_id' => self::clean($r[5] ?? ''),
                'enroller_id2' => self::clean($r[6] ?? ''),
                'msc' => self::clean($r[7] ?? ''),
                'msc1' => self::clean($r[8] ?? ''),
                'msc2' => self::clean($r[9] ?? ''),
                'ticket_id2' => self::clean($r[10] ?? ''),
                'status' => self::clean($r[11] ?? ''),
                'comment' => self::clean($r[12] ?? ''),
                'amount' => (float) self::clean($r[13] ?? ''),
                'date_enrolled' => self::clean($r[14] ?? ''),
                'timestamp1' => self::clean($r[15] ?? ''),
                'timestamp2' => self::clean($r[16] ?? ''),
                'timestamp3' => self::clean($r[17] ?? ''),
                'time_zone' => self::clean($r[18] ?? ''),
            ];
        }

        if (empty($records)) {
            return back()->withErrors(['file' => 'No valid rows found (each row needs a ticket ID in the first column).']);
        }

        $inserted = 0;
        $updated = 0;

        try {
            foreach (array_chunk($records, 100) as $batch) {
                DB::transaction(function () use ($batch, &$inserted, &$updated) {
                    foreach ($batch as $record) {
                        $model = Record::updateOrCreate(
                            ['ticket_id' => $record['ticket_id']],
                            $record
                        );
                        $model->wasRecentlyCreated ? $inserted++ : $updated++;
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('Enrolment upload persist error: '.$e->getMessage());

            return back()->withErrors(['file' => 'Failed to save records. Please try again.']);
        }

        return back()->with('success', "Upload completed — Inserted: {$inserted}, Updated: {$updated}, Total: ".count($records).'.');
    }

    /**
     * Strip a leading apostrophe (Excel "text" marker) and coerce to string.
     */
    private static function clean(mixed $value): string
    {
        $value = (string) $value;

        return str_starts_with($value, "'") ? substr($value, 1) : $value;
    }
}

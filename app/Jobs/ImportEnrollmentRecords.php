<?php

namespace App\Jobs;

use App\Models\EnrollmentImport;
use App\Support\SpreadsheetReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports a staged enrolment spreadsheet into the `Record` table.
 *
 * Runs on the queue because these files are 50-100MB (hundreds of thousands of
 * rows) — far past what an HTTP request can parse inside max_execution_time.
 *
 * Two things keep it inside a normal memory_limit and a sane runtime:
 *  - the file is *streamed* a row at a time (never materialised as an array);
 *  - rows are written with a chunked bulk `upsert` on the unique `ticket_id`,
 *    not row-by-row `updateOrCreate`. Re-uploading the same enrolment export
 *    therefore overwrites each ticket's status in place, which is the whole
 *    point: users read their enrolment status off these rows.
 */
class ImportEnrollmentRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Rows per upsert. 19 columns x 1000 rows = ~19k bind parameters, well
     * inside Postgres' 65535 limit with room for the timestamp columns.
     */
    private const CHUNK = 1000;

    public int $timeout = 3600;

    /**
     * No retries: a half-finished import has already committed its earlier
     * chunks, and re-running the whole file on a transient error would just
     * burn another hour. The admin can re-upload — it is idempotent.
     */
    public int $tries = 1;

    /**
     * Every column the upsert overwrites on conflict. `ticket_id` is the match
     * key and `createdAt` is deliberately absent so the original insert time
     * survives re-uploads.
     */
    private const UPDATE_COLUMNS = [
        'bvn', 'org_name', 'org_id', 'enrollee_name', 'enroller_id', 'enroller_id2',
        'msc', 'msc1', 'msc2', 'ticket_id2', 'status', 'comment', 'amount',
        'date_enrolled', 'timestamp1', 'timestamp2', 'timestamp3', 'time_zone',
        'updatedAt',
    ];

    public function __construct(public readonly string $importId)
    {
        // Its own queue: a 100MB import occupies a worker for minutes, and the
        // default queue fulfils customer data purchases. Sharing one worker
        // would stall live purchases behind an admin upload.
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $import = EnrollmentImport::find($this->importId);

        // Idempotent: only a freshly-queued import is eligible.
        if (! $import || $import->status !== 'pending') {
            return;
        }

        $import->update(['status' => 'processing', 'started_at' => now()]);

        $absolutePath = Storage::disk('local')->path($import->path);

        $rowsRead = 0;
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $batch = [];

        try {
            if (! is_file($absolutePath)) {
                throw new \RuntimeException('The uploaded file is no longer available on the server.');
            }

            foreach (SpreadsheetReader::stream($absolutePath, $import->extension) as $row) {
                $rowsRead++;

                if ($rowsRead === 1 && self::looksLikeHeader($row)) {
                    continue;
                }

                $ticketId = self::clean($row[0] ?? '');
                if ($ticketId === '') {
                    $skipped++;

                    continue;
                }

                $batch[$ticketId] = self::mapRow($ticketId, $row);

                if (count($batch) >= self::CHUNK) {
                    [$i, $u] = $this->flush($batch);
                    $inserted += $i;
                    $updated += $u;
                    $batch = [];

                    $import->update([
                        'rows_read' => $rowsRead,
                        'inserted' => $inserted,
                        'updated' => $updated,
                        'skipped' => $skipped,
                    ]);
                }
            }

            if ($batch !== []) {
                [$i, $u] = $this->flush($batch);
                $inserted += $i;
                $updated += $u;
            }

            $import->update([
                'status' => 'completed',
                'rows_read' => $rowsRead,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Enrolment import failed', [
                'import_id' => $import->id,
                'rows_read' => $rowsRead,
                'error' => $e->getMessage(),
            ]);

            $import->update([
                'status' => 'failed',
                'rows_read' => $rowsRead,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                // Rows already committed are kept — say so, so the admin knows
                // a re-upload is a resume rather than a duplicate.
                'error' => "Failed after {$rowsRead} rows: ".$e->getMessage(),
                'finished_at' => now(),
            ]);
        } finally {
            Storage::disk('local')->delete($import->path);
        }
    }

    public function failed(Throwable $e): void
    {
        // Covers the cases handle() can't catch (timeout, worker kill).
        EnrollmentImport::where('id', $this->importId)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'error' => 'Import stopped unexpectedly: '.$e->getMessage(),
                'finished_at' => now(),
            ]);
    }

    /**
     * Upsert one batch, returning [inserted, updated].
     *
     * The batch is keyed by ticket_id, so duplicates *within* a chunk collapse
     * to the last occurrence — Postgres' ON CONFLICT refuses to touch the same
     * row twice in one statement, and these exports do repeat tickets.
     *
     * @param  array<string, array<string, mixed>>  $batch
     * @return array{0: int, 1: int}
     */
    private function flush(array $batch): array
    {
        $ticketIds = array_keys($batch);

        // One extra SELECT per 1000 rows buys an accurate inserted/updated
        // split, which a bare upsert cannot report.
        $existing = DB::table('Record')
            ->whereIn('ticket_id', $ticketIds)
            ->count();

        DB::table('Record')->upsert(
            array_values($batch),
            ['ticket_id'],
            self::UPDATE_COLUMNS
        );

        return [count($ticketIds) - $existing, $existing];
    }

    /**
     * Positional column mapping (mirrors the source's r[0]..r[18]).
     *
     * @param  array<int, string>  $r
     * @return array<string, mixed>
     */
    private static function mapRow(string $ticketId, array $r): array
    {
        $now = now();

        return [
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
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    /**
     * The UI says a header row is optional, but without this the label row is
     * stored as a Record whose ticket_id is the column name.
     *
     * Matching against a list of known labels was too brittle — real exports
     * head this column "TICKET_NUMBER", "Ticket ID", "TICKET_NO" and so on.
     * Ticket numbers always contain digits and column labels never do, which
     * holds regardless of what the exporter calls the column.
     *
     * @param  array<int, string>  $row
     */
    private static function looksLikeHeader(array $row): bool
    {
        $first = self::clean($row[0] ?? '');

        return $first !== '' && ! preg_match('/\d/', $first);
    }

    /**
     * Strip a leading apostrophe (Excel "text" marker) and coerce to string.
     *
     * Empty cells reach us as the literal string "null" — the exporter writes
     * JavaScript nulls straight out, so ~38% of BVNs in a real export arrive as
     * "null". Stored verbatim those would display as the word "null" to users
     * checking their enrolment, and would match a search for "null".
     */
    private static function clean(mixed $value): string
    {
        $value = trim((string) $value);

        if (str_starts_with($value, "'")) {
            $value = substr($value, 1);
        }

        return strcasecmp($value, 'null') === 0 ? '' : $value;
    }
}

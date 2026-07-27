<?php

namespace Tests\Feature;

use App\Jobs\ImportEnrollmentRecords;
use App\Models\EnrollmentImport;
use App\Models\Record;
use App\Models\User;
use App\Support\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrollmentImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stage a CSV on the local disk exactly as the controller does, and return
     * the EnrollmentImport pointing at it.
     */
    private function stage(string $csv, string $ext = 'csv'): EnrollmentImport
    {
        Storage::fake('local');
        Storage::disk('local')->put("enrollment-imports/file.{$ext}", $csv);

        return EnrollmentImport::create([
            'original_name' => "records.{$ext}",
            'path' => "enrollment-imports/file.{$ext}",
            'extension' => $ext,
            'size' => strlen($csv),
            'status' => 'pending',
        ]);
    }

    private function row(string $ticket, string $status = 'APPROVED'): string
    {
        return implode(',', [
            $ticket, '22222222222', 'Org', 'ORG1', 'Jane Doe', 'E1', 'E2',
            'M', 'M1', 'M2', $ticket.'B', $status, 'ok', '500',
            '2026-01-01', 't1', 't2', 't3', 'WAT',
        ])."\n";
    }

    public function test_it_imports_rows_and_maps_columns_positionally(): void
    {
        $import = $this->stage($this->row('TK-1').$this->row('TK-2'));

        (new ImportEnrollmentRecords($import->id))->handle();

        $this->assertSame(2, Record::count());

        $record = Record::where('ticket_id', 'TK-1')->first();
        $this->assertSame('22222222222', $record->bvn);
        $this->assertSame('Jane Doe', $record->enrollee_name);
        $this->assertSame('APPROVED', $record->status);
        $this->assertSame(500.0, $record->amount);
        $this->assertSame('WAT', $record->time_zone);

        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->inserted);
        $this->assertSame(0, $import->updated);
    }

    public function test_reuploading_overwrites_the_row_for_the_same_ticket_id(): void
    {
        $first = $this->stage($this->row('TK-1', 'PENDING'));
        (new ImportEnrollmentRecords($first->id))->handle();

        $this->assertSame('PENDING', Record::where('ticket_id', 'TK-1')->value('status'));

        // The same ticket comes back in a later export with a new status —
        // this is the whole point of the feature.
        $second = $this->stage($this->row('TK-1', 'APPROVED'));
        (new ImportEnrollmentRecords($second->id))->handle();

        $this->assertSame(1, Record::count(), 'Re-upload must overwrite, not duplicate');
        $this->assertSame('APPROVED', Record::where('ticket_id', 'TK-1')->value('status'));

        $second->refresh();
        $this->assertSame(1, $second->updated);
        $this->assertSame(0, $second->inserted);
    }

    public function test_it_collapses_duplicate_ticket_ids_within_one_batch(): void
    {
        // Postgres ON CONFLICT refuses to touch the same row twice in one
        // statement, so a file repeating a ticket must not reach the upsert twice.
        $import = $this->stage($this->row('TK-1', 'PENDING').$this->row('TK-1', 'APPROVED'));

        (new ImportEnrollmentRecords($import->id))->handle();

        $this->assertSame(1, Record::count());
        $this->assertSame('APPROVED', Record::where('ticket_id', 'TK-1')->value('status'), 'Last occurrence wins');
    }

    public function test_it_skips_a_header_row_and_rows_without_a_ticket_id(): void
    {
        $csv = "Ticket ID,BVN,Org Name\n".$this->row('TK-1').",,,\n";
        $import = $this->stage($csv);

        (new ImportEnrollmentRecords($import->id))->handle();

        $this->assertSame(['TK-1'], Record::pluck('ticket_id')->all());
        $this->assertSame(1, $import->refresh()->skipped);
    }

    /**
     * Real exports head this column "TICKET_NUMBER", not "Ticket ID".
     */
    public function test_it_skips_a_header_row_whatever_the_column_is_called(): void
    {
        $csv = "TICKET_NUMBER,BVN,AGT_MGT_INST_NAME\n".$this->row('83938224260516115957');
        $import = $this->stage($csv);

        (new ImportEnrollmentRecords($import->id))->handle();

        $this->assertSame(['83938224260516115957'], Record::pluck('ticket_id')->all());
        $this->assertSame(0, Record::where('ticket_id', 'TICKET_NUMBER')->count());
    }

    /**
     * The exporter writes JavaScript nulls literally, and quotes values with a
     * leading apostrophe: a missing BVN arrives as the 5 characters 'null.
     */
    public function test_it_normalises_literal_null_cells_to_empty(): void
    {
        $csv = "'83938224260516115957,'null,KAYI MICROFINANCE BANK LTD,12590,NULL,,,,,,,FAILED,msg,100,,,,\n";
        $import = $this->stage($csv);

        (new ImportEnrollmentRecords($import->id))->handle();

        $record = Record::first();
        $this->assertSame('83938224260516115957', $record->ticket_id, 'leading apostrophe stripped');
        $this->assertSame('', $record->bvn, "'null must not be stored as the word null");
        $this->assertSame('', $record->enrollee_name, 'bare NULL is normalised too, case-insensitively');
        $this->assertSame('FAILED', $record->status);
    }

    public function test_it_strips_a_utf8_bom_from_the_first_ticket_id(): void
    {
        $import = $this->stage("\xEF\xBB\xBF".$this->row('TK-1'));

        (new ImportEnrollmentRecords($import->id))->handle();

        $this->assertSame(['TK-1'], Record::pluck('ticket_id')->all());
    }

    public function test_it_marks_the_import_failed_and_deletes_the_staged_file(): void
    {
        $import = $this->stage($this->row('TK-1'));
        Storage::disk('local')->delete($import->path);

        (new ImportEnrollmentRecords($import->id))->handle();

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('no longer available', $import->error);
    }

    public function test_the_staged_file_is_deleted_after_a_successful_import(): void
    {
        $import = $this->stage($this->row('TK-1'));

        (new ImportEnrollmentRecords($import->id))->handle();

        Storage::disk('local')->assertMissing($import->path);
    }

    /**
     * ValidatePostSize throws from the global middleware stack before any
     * controller runs, so this cannot be covered by the controller's own
     * checks. Unhandled it renders Symfony's bare "413 Content Too Large" page.
     */
    public function test_a_post_over_post_max_size_redirects_back_with_a_useful_message(): void
    {
        // CONTENT_LENGTH has to go in the server bag, not the headers array —
        // Laravel rewrites unknown headers to HTTP_*, which ValidatePostSize
        // does not read.
        $response = $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.enrollment-records.index'))
            ->call(
                'POST',
                route('admin.enrollment-records.upload'),
                [], [], [],
                ['CONTENT_LENGTH' => (string) (1024 * 1024 * 1024)]
            );

        $response->assertRedirect(route('admin.enrollment-records.index'));
        $response->assertSessionHasErrors('file');

        $this->assertStringContainsString(
            'post_max_size',
            session('errors')->first('file'),
            'The admin must be told which limit blocked the upload'
        );
    }

    /**
     * The .xlsx path is parsed with XMLReader rather than a DOM, so it needs
     * its own coverage: shared strings, inline strings, numeric cells, and
     * gaps in the column run (a skipped <c> must not shift later columns left).
     */
    public function test_it_parses_xlsx_shared_inline_and_numeric_cells(): void
    {
        $stub = tempnam(sys_get_temp_dir(), 'enrol');
        $path = $stub.'.xlsx';
        unlink($stub);

        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<si><t>TK-9</t></si><si><t>Jane Doe</t></si></sst>';

        // Column D is absent entirely; E must still land at index 4.
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'
            .'<c r="A1" t="s"><v>0</v></c>'
            .'<c r="B1" t="inlineStr"><is><t>22222222222</t></is></c>'
            .'<c r="C1"><v>123</v></c>'
            .'<c r="E1" t="s"><v>1</v></c>'
            .'</row></sheetData></worksheet>';

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $rows = iterator_to_array(SpreadsheetReader::stream($path, 'xlsx'), false);
        unlink($path);

        $this->assertCount(1, $rows);
        $this->assertSame('TK-9', $rows[0][0], 'shared string');
        $this->assertSame('22222222222', $rows[0][1], 'inline string');
        $this->assertSame('123', $rows[0][2], 'numeric cell');
        $this->assertSame('', $rows[0][3], 'gap is filled, not collapsed');
        $this->assertSame('Jane Doe', $rows[0][4], 'column after the gap keeps its index');
    }

    public function test_a_corrupt_xlsx_reports_a_readable_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'enrol').'.xlsx';
        file_put_contents($path, 'this is not a zip archive');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/corrupt|not a real/i');
            iterator_to_array(SpreadsheetReader::stream($path, 'xlsx'), false);
        } finally {
            unlink($path);
        }
    }

    /**
     * The reason this whole feature was rebuilt: a 100MB file must not be
     * held in memory. 200k rows is ~20MB on disk; streaming it should cost
     * near-constant memory regardless of length.
     */
    public function test_streaming_a_large_csv_uses_bounded_memory(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'enrol').'.csv';
        $handle = fopen($path, 'w');
        for ($i = 0; $i < 200000; $i++) {
            fwrite($handle, $this->row('TK-'.$i));
        }
        fclose($handle);

        $this->assertGreaterThan(15 * 1024 * 1024, filesize($path), 'Fixture should be large enough to matter');

        $before = memory_get_usage(true);
        $count = 0;
        $lastTicket = null;

        foreach (SpreadsheetReader::stream($path, 'csv') as $row) {
            $count++;
            $lastTicket = $row[0];
        }

        $growth = memory_get_usage(true) - $before;
        unlink($path);

        $this->assertSame(200000, $count);
        $this->assertSame('TK-199999', $lastTicket);
        $this->assertLessThan(
            8 * 1024 * 1024,
            $growth,
            'Streaming a 20MB CSV grew memory by '.round($growth / 1024 / 1024, 1).'MB — it is buffering rows'
        );
    }
}

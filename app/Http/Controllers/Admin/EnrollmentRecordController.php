<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportEnrollmentRecords;
use App\Models\EnrollmentImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * Enrollment records upload — admin side.
 *
 * Port of nimcweb app/(Adminn)/admin/enrollment_records + its
 * /api/upload/bvnenrolments handler: the admin uploads a spreadsheet of BVN
 * enrolment rows which are upserted (by ticket_id) into the `Record` table.
 *
 * Real enrolment exports run 50-100MB, so this controller only *stages* the
 * file and queues {@see ImportEnrollmentRecords}. Parsing inline would blow
 * both memory_limit and max_execution_time long before it finished.
 *
 * The source used the SheetJS `xlsx` reader; here we parse .xlsx/.csv natively
 * (see App\Support\SpreadsheetReader) to avoid adding a composer dependency.
 * That reader streams CSV without a size ceiling, but .xlsx needs its shared
 * string table resident — hence the much lower cap on .xlsx below.
 */
class EnrollmentRecordController extends Controller
{
    /** 200MB — comfortably above the 50-100MB exports, in kilobytes. */
    private const MAX_CSV_KB = 204800;

    /** 20MB — .xlsx cannot be fully streamed; see SpreadsheetReader. */
    private const MAX_XLSX_KB = 20480;

    public function index()
    {
        return Inertia::render('Admin/EnrollmentRecords/Index', [
            'imports' => EnrollmentImport::latest()->limit(10)->get()
                ->map(fn (EnrollmentImport $i) => $this->present($i)),
            'limits' => [
                'csv_mb' => (int) (self::MAX_CSV_KB / 1024),
                'xlsx_mb' => (int) (self::MAX_XLSX_KB / 1024),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        // When a POST exceeds post_max_size, PHP discards the whole body: $_FILES
        // and $_POST arrive empty and Laravel's `required` reports a missing
        // file, which reads as a bug rather than a limit. Catch it explicitly.
        if ($request->file('file') === null && (int) $request->server('CONTENT_LENGTH', 0) > 0 && $request->all() === []) {
            return back()->withErrors(['file' => sprintf(
                'The upload exceeded the server limit (post_max_size = %s). Ask your host to raise post_max_size and upload_max_filesize in php.ini, and client_max_body_size in nginx.',
                ini_get('post_max_size') ?: 'unknown'
            )]);
        }

        if (! $request->hasFile('file')) {
            return back()->withErrors(['file' => 'Please choose a file to upload.']);
        }

        $file = $request->file('file');

        if (! $file->isValid()) {
            return back()->withErrors(['file' => $this->uploadErrorMessage($file)]);
        }

        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            $hint = $ext === 'xls'
                ? ' Legacy .xls is not supported — re-save the file as .xlsx or CSV.'
                : ' Please upload a .xlsx or .csv file.';

            return back()->withErrors(['file' => 'Unsupported file type: .'.$ext.'.'.$hint]);
        }

        $sizeKb = (int) ceil($file->getSize() / 1024);
        $maxKb = $ext === 'xlsx' ? self::MAX_XLSX_KB : self::MAX_CSV_KB;

        if ($sizeKb > $maxKb) {
            $message = sprintf(
                'This file is %s, over the %s limit for .%s uploads.',
                $this->humanKb($sizeKb),
                $this->humanKb($maxKb),
                $ext
            );

            // The .xlsx ceiling is low enough that admins will hit it with a
            // normal export, so point at the way out rather than just refusing.
            if ($ext === 'xlsx') {
                $message .= ' Excel files this large cannot be parsed safely — in Excel choose File → Save As → CSV and upload that instead (CSV is supported up to '
                    .$this->humanKb(self::MAX_CSV_KB).').';
            }

            return back()->withErrors(['file' => $message]);
        }

        $import = EnrollmentImport::create([
            'user_id' => $request->user()?->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('enrollment-imports', 'local'),
            'extension' => $ext,
            'size' => $file->getSize(),
            'status' => 'pending',
        ]);

        ImportEnrollmentRecords::dispatch($import->id);

        return back()->with('success', 'Upload received — importing '.$import->original_name.' in the background. Progress is shown below.');
    }

    /**
     * Polled by the upload screen while an import is running.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'imports' => EnrollmentImport::latest()->limit(10)->get()
                ->map(fn (EnrollmentImport $i) => $this->present($i)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EnrollmentImport $import): array
    {
        return [
            'id' => $import->id,
            'original_name' => $import->original_name,
            'size' => $this->humanKb((int) ceil($import->size / 1024)),
            'status' => $import->status,
            'summary' => $import->summary(),
            'rows_read' => $import->rows_read,
            'inserted' => $import->inserted,
            'updated' => $import->updated,
            'skipped' => $import->skipped,
            'finished' => $import->isFinished(),
            'created_at' => $import->created_at?->diffForHumans(),
        ];
    }

    /**
     * Turn PHP's upload error code into something an admin can act on.
     */
    private function uploadErrorMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => sprintf(
                'The file is larger than the server allows (upload_max_filesize = %s). Ask your host to raise upload_max_filesize and post_max_size in php.ini.',
                ini_get('upload_max_filesize') ?: 'unknown'
            ),
            UPLOAD_ERR_FORM_SIZE => 'The file exceeded the form\'s size limit.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted and only part of the file arrived. Please try again — a stable connection matters for files this size.',
            UPLOAD_ERR_NO_FILE => 'No file was received. Please choose a file and try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary upload directory configured. This needs a server administrator.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to disk — it may be out of space.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
            default => 'The file could not be uploaded. Please try again.',
        };
    }

    private function humanKb(int $kb): string
    {
        return $kb >= 1024
            ? round($kb / 1024, $kb >= 10240 ? 0 : 1).'MB'
            : $kb.'KB';
    }
}

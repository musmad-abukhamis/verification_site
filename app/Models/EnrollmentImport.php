<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;

/**
 * A single enrollment-records spreadsheet upload and its import progress.
 *
 * @see \App\Jobs\ImportEnrollmentRecords
 */
class EnrollmentImport extends Model
{
    use HasPrismaId;

    protected $table = 'enrollment_imports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'rows_read' => 'integer',
            'inserted' => 'integer',
            'updated' => 'integer',
            'skipped' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    /**
     * Human-readable one-liner for the admin screen.
     */
    public function summary(): string
    {
        return match ($this->status) {
            'pending' => 'Queued — waiting for a worker to pick it up.',
            'processing' => "Importing… {$this->rows_read} rows read so far.",
            'completed' => "Completed — inserted {$this->inserted}, updated {$this->updated}, skipped {$this->skipped} (of {$this->rows_read} rows).",
            'failed' => $this->error ?: 'Import failed.',
            default => (string) $this->status,
        };
    }
}

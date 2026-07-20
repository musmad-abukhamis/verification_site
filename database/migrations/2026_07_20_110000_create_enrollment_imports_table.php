<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per enrollment-records spreadsheet upload.
 *
 * Not part of the Prisma port -- new table. Enrolment files run 50-100MB
 * (hundreds of thousands of rows), which cannot be parsed inside an HTTP
 * request: the upload is staged to disk and handed to a queued job, so the
 * admin needs somewhere to watch progress and read the outcome afterwards.
 *
 * `path` is the staged file on the local disk. It is deleted once the import
 * finishes (either way) -- these files are large and the Record rows are the
 * durable artefact, not the spreadsheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_imports', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('user_id')->nullable();       // admin who uploaded
            $table->string('original_name');
            $table->string('path');                      // staged file, relative to the local disk
            $table->string('extension', 10);
            $table->unsignedBigInteger('size')->default(0);

            $table->string('status')->default('pending'); // pending | processing | completed | failed

            // Progress counters, updated as the job walks the file so the
            // admin sees movement on a file that takes minutes to import.
            $table->unsignedBigInteger('rows_read')->default(0);
            $table->unsignedBigInteger('inserted')->default(0);
            $table->unsignedBigInteger('updated')->default(0);
            $table->unsignedBigInteger('skipped')->default(0);

            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_imports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per CSV upload, queued and processed by ProcessCsvImport - the
     * frontend polls this row for its status rather than waiting on the
     * request. The uploaded file itself is deleted once processed (success
     * or failure); its path is never kept longer than the job needs it.
     */
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->string('status')->default('pending');
            $table->boolean('overwrite')->default(false);
            $table->string('original_filename');
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            // [{row: int, messages: string[]}, ...] - a file-level failure
            // (e.g. wrong headers) is stored as row 0.
            $table->jsonb('errors')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};

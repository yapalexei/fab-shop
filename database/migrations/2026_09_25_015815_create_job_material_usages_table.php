<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actual material consumed against a job - separate from the quote's
     * line items, which are the *estimate* a job's price is fixed to (see
     * Job::total's own note). This is the "estimate vs actual" ledger: it
     * only ever grows, even if the job's status is later walked back - a
     * reversed job keeps the cost of what was already used or wasted.
     */
    public function up(): void
    {
        Schema::create('job_material_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('fabrication_jobs')->cascadeOnDelete();
            // Null on delete, like every other catalog reference from a
            // historical record - removing a material from the catalog
            // doesn't erase what was already logged against a job.
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2);
            // 'used' or 'scrap' (App\Enums\MaterialUsageType) - scrap still
            // costs the shop money, but tracked separately so waste is
            // visible on its own, not folded silently into "used."
            $table->string('type');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_material_usages');
    }
};

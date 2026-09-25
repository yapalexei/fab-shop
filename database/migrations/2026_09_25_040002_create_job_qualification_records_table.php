<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what procedure on a job, and whether they held a valid
     * certification for it at the time - deliberately separate from
     * Certification itself (same reasoning as job_material_usages vs.
     * quote_line_items): a certification can later expire or be edited,
     * but this ledger's was_qualified snapshot must stay exactly what was
     * true when the work happened, for it to mean anything as an audit
     * trail. It only ever grows, same as job_material_usages.
     */
    public function up(): void
    {
        Schema::create('job_qualification_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('fabrication_jobs')->cascadeOnDelete();
            // No cascade/null-on-delete: a procedure still referenced here
            // can't be deleted (see procedures migration).
            $table->foreignId('procedure_id')->nullable()->constrained();
            // Who performed the work - nullable only so the row can survive
            // that person's account being removed later; always required at
            // the form level.
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description');
            $table->date('performed_at');
            // Null when no procedure was selected (nothing to check against).
            // Computed once at creation from whether performed_by held a
            // certification for procedure_id covering performed_at - never
            // recomputed later, so an expired cert doesn't retroactively
            // flag old, compliant work.
            $table->boolean('was_qualified')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_qualification_records');
    }
};

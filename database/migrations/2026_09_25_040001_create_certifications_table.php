<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A person's qualification, optionally tied to one of the shop's own
     * procedures - optional because plenty of real certs (OSHA 10, a
     * forklift card) aren't tied to a shop-defined procedure at all. When
     * it is tied to one, JobQualificationRecordController checks it to
     * snapshot whether the work was actually covered.
     */
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            // Meaningless without a person - unlike a catalog record, there's
            // no freeform fallback, so this cascades rather than nulling.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // No cascade/null-on-delete: a procedure still referenced by a
            // certification can't be deleted (see procedures migration).
            $table->foreignId('procedure_id')->nullable()->constrained();
            $table->string('cert_number')->nullable();
            $table->string('issuing_body')->nullable();
            $table->date('issued_at')->nullable();
            // Null means it doesn't expire.
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};

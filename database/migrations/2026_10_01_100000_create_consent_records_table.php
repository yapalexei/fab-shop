<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail of what people agreed to (Terms, Privacy
     * Policy, SMS messaging), when, how, and the exact wording they saw.
     * Withdrawing consent adds a new row (granted = false) rather than
     * editing the old one. Nothing here is tenant-scoped or exposed in the
     * UI: it exists to be produced for an audit or carrier review.
     *
     * user_id/shop_id are nullOnDelete so deleting an account never erases
     * the proof; subject_email/subject_phone keep who it was.
     */
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_email')->nullable();
            // E.164 for SMS consent.
            $table->string('subject_phone', 32)->nullable();
            $table->string('type');
            $table->boolean('granted');
            $table->string('document_version');
            // The checkbox/notice wording exactly as shown.
            $table->text('consent_text');
            // Where it was given: registration, invitation, profile...
            $table->string('source');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type']);
            $table->index(['subject_phone', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};

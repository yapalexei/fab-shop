<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->string('quickbooks_invoice_id')->nullable()->after('status');
            $table->timestamp('quickbooks_synced_at')->nullable()->after('quickbooks_invoice_id');
            $table->text('quickbooks_sync_error')->nullable()->after('quickbooks_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->dropColumn(['quickbooks_invoice_id', 'quickbooks_synced_at', 'quickbooks_sync_error']);
        });
    }
};

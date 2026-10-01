<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sending a quote to the customer is optional now: `emailed_at` records
     * when the email actually went out, `send_skipped_reason` why it was
     * deliberately not sent (e.g. agreed verbally).
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('sent_at');
            $table->string('send_skipped_reason')->nullable()->after('emailed_at');
        });

        // Every quote sent before this existed went out by email.
        DB::table('quotes')->whereNotNull('sent_at')->update(['emailed_at' => DB::raw('sent_at')]);
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['emailed_at', 'send_skipped_reason']);
        });
    }
};

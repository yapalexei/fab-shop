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
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable()->after('plan_id');
            $table->timestamp('trial_ended_notified_at')->nullable()->after('trial_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['trial_ends_at', 'trial_ended_notified_at']);
        });
    }
};

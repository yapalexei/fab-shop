<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per member, per shop: when they closed the "your trial ended" notice.
     * On the pivot rather than users because the notice is about one shop's
     * trial - dismissing it in Shop A must not hide it in Shop B.
     */
    public function up(): void
    {
        Schema::table('shop_user', function (Blueprint $table) {
            $table->timestamp('trial_ended_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_user', function (Blueprint $table) {
            $table->dropColumn('trial_ended_dismissed_at');
        });
    }
};

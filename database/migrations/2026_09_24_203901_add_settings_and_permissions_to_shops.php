<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All nullable: null means "use config('shop.defaults.*')".
        Schema::table('shops', function (Blueprint $table) {
            $table->string('timezone')->nullable();
            $table->unsignedSmallInteger('stale_quote_days')->nullable();
            $table->unsignedSmallInteger('win_rate_window_days')->nullable();
        });

        // Per-member overrides on top of the member's role, keyed by
        // permission name: true = always allow, false = never allow, absent =
        // inherit from the role.
        Schema::table('shop_user', function (Blueprint $table) {
            $table->jsonb('permission_overrides')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_user', function (Blueprint $table) {
            $table->dropColumn('permission_overrides');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'stale_quote_days', 'win_rate_window_days']);
        });
    }
};

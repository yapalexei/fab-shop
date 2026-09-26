<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable - null means "use the shop's plan's own user_limit," same
     * "nullable column overrides a default" shape as timezone/
     * stale_quote_days/win_rate_window_days, except the fallback here is
     * another model's column (Plan::user_limit) rather than a config value.
     * A platform admin can raise (or lower) a specific shop's seat count
     * without needing a whole separate Plan row just for that one shop.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedInteger('seat_limit_override')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('seat_limit_override');
        });
    }
};

<?php

use App\Enums\ShopPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Permission rows are global (only role/user assignments are
        // per-shop), so they're created once here rather than per shop.
        foreach (ShopPermission::cases() as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission->value,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_column(ShopPermission::cases(), 'value'))->delete();

        Schema::table('shop_user', function (Blueprint $table) {
            $table->dropColumn('permission_overrides');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'stale_quote_days', 'win_rate_window_days']);
        });
    }
};

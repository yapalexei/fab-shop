<?php

use App\Models\Shop;
use App\Support\ShopPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The first two shop permissions predate the "{resource}.{action}"
     * naming - renamed in place so existing grants and overrides carry over.
     */
    private const RENAMED = [
        'view-financials' => 'financials.view',
        'manage-shop-settings' => 'shop-settings.update',
    ];

    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Null = config('shop.defaults.job_board_drag_enabled').
            $table->boolean('job_board_drag_enabled')->nullable();
        });

        foreach (self::RENAMED as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        DB::table('shop_user')->whereNotNull('permission_overrides')->orderBy('id')->each(function ($row) {
            $overrides = json_decode($row->permission_overrides, true);
            $renamed = collect($overrides)->mapWithKeys(fn ($value, $key) => [self::RENAMED[$key] ?? $key => $value])->all();

            DB::table('shop_user')->where('id', $row->id)->update(['permission_overrides' => json_encode($renamed)]);
        });

        // Every existing shop's Admin/Staff get the new defaults - without
        // this, adding permission checks would lock existing teams out of
        // everything they could do before. Only adds; nothing is revoked.
        Shop::query()->each(fn (Shop $shop) => ShopPermissions::seedRoles($shop));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('permissions')->where('name', $new)->update(['name' => $old]);
        }

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('job_board_drag_enabled');
        });
    }
};

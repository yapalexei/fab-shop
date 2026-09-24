<?php

use App\Models\Shop;
use App\Support\ShopPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Give existing shops' Admin/Staff roles the tooling.* defaults, same as
     * a new shop gets. Adds only - nothing an owner already chose is revoked.
     */
    public function up(): void
    {
        Shop::query()->each(fn (Shop $shop) => ShopPermissions::seedRoles($shop));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};

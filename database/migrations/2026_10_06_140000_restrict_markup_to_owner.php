<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Markup (what a shop adds on top of its costs) is now the owner's
     * alone unless they hand it out per member, so Admin and Staff no
     * longer get markup.view / markup.update from their role. New shops
     * start that way (ShopPermissions::defaultsFor); this takes the old
     * defaults back from existing shops. Per-member overrides on shop_user
     * are untouched, so anyone the owner had individually allowed keeps it.
     */
    public function up(): void
    {
        $permissions = DB::table('permissions')->whereIn('name', ['markup.view', 'markup.update'])->pluck('id');
        $roles = DB::table('roles')->whereIn('name', ['Admin', 'Staff'])->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissions)
            ->whereIn('role_id', $roles)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};

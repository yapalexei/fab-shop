<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A platform-level staff flag, entirely separate from the per-shop
     * Owner/Admin/Staff roles - it crosses tenant boundaries on purpose, so
     * it's granted out-of-band (tinker, or the app:grant-platform-admin
     * command), never through any user-facing form.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('current_shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};

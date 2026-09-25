<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set only by the platform admin console, never through any shop-facing
     * update path - deliberately left out of Shop's $fillable, same pattern
     * as onboarded_at/current_shop_id elsewhere in the app.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('onboarding_step');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
    }
};

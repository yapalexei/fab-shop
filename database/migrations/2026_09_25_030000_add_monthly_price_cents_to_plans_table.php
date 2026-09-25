<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the shop is nominally billed per month for this plan - used to
     * compute potential revenue on the platform admin dashboard. Not tied to
     * any payment processor; a real Stripe/Square integration would source
     * actual collected revenue separately from this.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('monthly_price_cents')->nullable()->after('user_limit');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('monthly_price_cents');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How often the shop's plan and add-ons renew together (one shared
        // billing date; shops.subscription_renews_at is the current period's end).
        Schema::table('shops', function (Blueprint $table) {
            $table->string('billing_interval')->default('monthly')->after('subscription_renews_at');
        });

        // Cancelling an add-on keeps it until the period already paid for
        // ends: cancelled_at is when it was cancelled, ends_at when access stops.
        Schema::table('shop_feature_subscriptions', function (Blueprint $table) {
            $table->timestamp('ends_at')->nullable()->after('cancelled_at');
        });

        DB::table('shop_feature_subscriptions')->whereNotNull('cancelled_at')->update(['ends_at' => DB::raw('cancelled_at')]);
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('billing_interval'));
        Schema::table('shop_feature_subscriptions', fn (Blueprint $table) => $table->dropColumn('ends_at'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manually-set "paid through" date - there's no real payment
     * processor wired up (F0.8 Stripe/Cashier was never built), so this is
     * a platform admin's own record of when a shop last paid outside the
     * app, not something derived automatically. Nullable means "never
     * tracked" - distinct from an actual past date, which means "overdue."
     * Deliberately not a separate table: this is a single 1:1 fact about a
     * shop (like trial_ends_at/suspended_at already are), not a history of
     * multiple past subscriptions worth its own rows.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('subscription_renews_at')->nullable()->after('trial_baseline_days');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('subscription_renews_at');
        });
    }
};

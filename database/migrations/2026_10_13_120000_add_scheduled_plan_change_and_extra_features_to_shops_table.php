<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a tier's price drops, shops already on an older version of it move
     * to the new price at the end of their current paid period: the plan to
     * move to and the moment it takes effect are held here until then.
     *
     * `extra_features` are features a shop has on top of its plan row. A shop
     * keeps every feature it had even if a newer version of the tier drops
     * one, and gains features added to the tier later, without moving off the
     * version (and price) it signed up for.
     *
     * `plan_notice` is the latest "here is what changed on your plan" summary
     * (features gained or retired, price, seats) shown on the Plan page until
     * the owner dismisses it. `notified_discontinued_features` remembers which
     * discontinued features the owner has already been told about.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->timestamp('pending_plan_effective_at')->nullable();
            $table->json('extra_features')->nullable();
            $table->json('plan_notice')->nullable();
            $table->json('notified_discontinued_features')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropColumn(['pending_plan_effective_at', 'extra_features', 'plan_notice', 'notified_discontinued_features']);
        });
    }
};

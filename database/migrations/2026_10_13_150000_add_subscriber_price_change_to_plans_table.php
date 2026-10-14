<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set on the version an admin publishes. A lower price always reaches
     * shops already on the tier straight away; these only matter for a
     * *higher* one: whether it is applied to existing subscribers at all, and
     * from which day. A null date means immediately; the admin form proposes
     * `shop.price_increase_notice_days` from the day of the change.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('apply_to_subscribers')->default(false);
            $table->date('subscribers_effective_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['apply_to_subscribers', 'subscribers_effective_on']);
        });
    }
};

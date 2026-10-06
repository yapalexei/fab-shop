<?php

use Database\Seeders\PlanSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Monthly price of one seat beyond the plan's user_limit. Null =
            // the plan can't buy extras (Solo is one person; move to Shop).
            $table->unsignedInteger('extra_seat_price_cents')->nullable()->after('monthly_price_cents');
        });

        Schema::table('shops', function (Blueprint $table) {
            // Seats bought on top of the plan's user_limit.
            $table->unsignedInteger('extra_seats')->default(0)->after('seat_limit_override');
        });

        // Applies the reorganized plans (the previous migration defers to
        // this one on a database that already had plans) including the
        // extra-seat prices. Idempotent, and skipped on an unseeded database.
        if (DB::table('plans')->exists()) {
            (new PlanSeeder)->run();
        }
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('extra_seats'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('extra_seat_price_cents'));
    }
};

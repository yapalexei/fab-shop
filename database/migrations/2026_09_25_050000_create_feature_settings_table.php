<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feature_settings', function (Blueprint $table) {
            $table->id();
            $table->string('feature')->unique();
            // Placeholder per-feature add-on price, pending real pricing -
            // platform admins adjust this from the platform admin dashboard.
            $table->unsignedInteger('price_cents')->default(2000);
            // Matches Shop::TRIAL_DAYS by default; editable per feature (or
            // in bulk) from the same dashboard.
            $table->unsignedInteger('trial_days')->default(14);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_settings');
    }
};

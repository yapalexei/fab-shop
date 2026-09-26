<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One global row (no shop_id - platform-wide, like feature_settings)
     * for settings that apply to the app as a whole rather than to one
     * feature or one shop. Starts with just the default trial length new
     * shops get; PlatformSetting::current() lazily creates the row.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('default_trial_days')->default(14);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};

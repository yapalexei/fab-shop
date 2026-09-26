<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the platform-wide default trial length actually was when this
     * shop signed up, captured once and never touched again. Needed because
     * Shop::onTrialFor()/enabledFeatures() compute a feature's own trial
     * window as an *offset* from this baseline (see their doc comments) -
     * now that the default itself is admin-editable
     * (PlatformSetting::defaultTrialDays()), that offset math would silently
     * go wrong for every existing shop the moment an admin changes the
     * default, unless each shop keeps its own historical baseline instead
     * of reading whatever the current global value happens to be.
     *
     * Nullable, falling back to the old hardcoded Shop::TRIAL_DAYS (see
     * Shop::trialBaselineDays()) - every shop that existed before this
     * column did was in fact created under that constant, so the fallback
     * is exact, not a guess, and no backfill is needed.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedInteger('trial_baseline_days')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('trial_baseline_days');
        });
    }
};

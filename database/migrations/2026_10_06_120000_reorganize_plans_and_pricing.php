<?php

use App\Enums\Feature;
use App\Models\PlatformSetting;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // New prices, user limits and feature sets for Solo / Shop (key
        // 'start-up') / Pro, including the new ai-agent feature on Pro.
        // The seeder is idempotent (updateOrCreate + syncFeatures), so it is
        // the one place the plan definitions live. Nobody is billed yet (no
        // payment processor is integrated), so there is no one to grandfather.
        // Skipped on a database that was never seeded - `db:seed` will
        // create the plans with these definitions anyway.
        if (DB::table('plans')->exists()) {
            (new PlanSeeder)->run();
        }

        // Add-on prices: only rows still at the old $20 placeholder, so an
        // admin's own edits survive.
        DB::table('feature_settings')
            ->whereIn('feature', [Feature::MaterialTracking->value, Feature::QualificationRecords->value])
            ->where('price_cents', 2000)
            ->update(['price_cents' => 2500]);

        // Longer default trial for new signups; existing shops keep their own
        // baseline (shops.trial_baseline_days), so their trials are untouched.
        // (A feature/platform row that doesn't exist yet is created lazily at
        // the new default, so only existing rows need moving.)
        //
        // Feature trial windows are stored per feature (and read as an offset
        // from each shop's own baseline), so rows still at the old default
        // move with it. Existing shops' own windows therefore run longer by
        // the difference - acceptable pre-launch, and a one-off.
        DB::table('platform_settings')->where('default_trial_days', 14)->update(['default_trial_days' => PlatformSetting::DEFAULT_TRIAL_DAYS]);
        DB::table('feature_settings')->where('trial_days', 14)->update(['trial_days' => PlatformSetting::DEFAULT_TRIAL_DAYS]);
        PlatformSetting::forgetCache();
    }

    public function down(): void
    {
        DB::table('feature_settings')
            ->whereIn('feature', [Feature::MaterialTracking->value, Feature::QualificationRecords->value])
            ->where('price_cents', 2500)
            ->update(['price_cents' => 2000]);

        DB::table('platform_settings')->where('default_trial_days', PlatformSetting::DEFAULT_TRIAL_DAYS)->update(['default_trial_days' => 14]);
        DB::table('feature_settings')->where('trial_days', PlatformSetting::DEFAULT_TRIAL_DAYS)->update(['trial_days' => 14]);
        PlatformSetting::forgetCache();
    }
};

<?php

use App\Enums\Feature;
use App\Models\Shop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-quote change log (the paid `quote-history` feature). Rows are only
     * written and read for shops entitled to the feature - see
     * App\Support\QuoteHistory.
     */
    public function up(): void
    {
        Schema::create('quote_history_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            // Kept (as "Someone") if the person is later removed.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('summary');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'created_at']);
        });

        // Pro bundles every feature, and a new feature needs its add-on
        // price row - both are seeded data a deploy wouldn't otherwise get.
        $proId = DB::table('plans')->where('key', 'pro')->value('id');
        if ($proId !== null) {
            DB::table('plan_feature')->insertOrIgnore([
                'plan_id' => $proId,
                'feature' => Feature::QuoteHistory->value,
            ]);
        }

        // Only an install that already has add-on pricing needs the new row
        // (a fresh one gets every row from FeatureSetting::ensureDefaults()).
        if (DB::table('feature_settings')->exists()) {
            DB::table('feature_settings')->insertOrIgnore([
                'feature' => Feature::QuoteHistory->value,
                'price_cents' => Feature::QuoteHistory->defaultPriceCents(),
                'trial_days' => DB::table('platform_settings')->value('default_trial_days') ?? Shop::TRIAL_DAYS,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_history_entries');

        DB::table('plan_feature')->where('feature', Feature::QuoteHistory->value)->delete();
        DB::table('feature_settings')->where('feature', Feature::QuoteHistory->value)->delete();
    }
};

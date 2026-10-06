<?php

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Off until a shop turns it on in its settings.
            $table->boolean('quote_emailing_enabled')->default(false);
        });

        // Emailing quotes is part of Start-up and Pro. Existing databases
        // were seeded before the feature existed, so add it to the plans.
        foreach (Plan::query()->whereIn('key', ['start-up', 'pro'])->get() as $plan) {
            DB::table('plan_feature')->updateOrInsert([
                'plan_id' => $plan->id,
                'feature' => Feature::QuoteEmailing->value,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plan_feature')->where('feature', Feature::QuoteEmailing->value)->delete();

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('quote_emailing_enabled');
        });
    }
};

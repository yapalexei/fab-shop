<?php

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shop's own AI provider configuration (bring-your-own-key). One row
     * per shop. The key is encrypted at rest by the model's cast and never
     * sent back to the browser.
     */
    public function up(): void
    {
        Schema::create('shop_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            // 'byok' today; room for a fab-shop hosted source later.
            $table->string('source', 20)->default('byok');
            $table->string('provider', 30);
            $table->string('model', 100);
            // Encrypted payload, so far longer than the raw key.
            $table->text('api_key')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        // AI assist is part of Start-up and Pro. Existing databases were
        // seeded before the feature existed, so add it to the plans.
        foreach (Plan::query()->whereIn('key', ['start-up', 'pro'])->get() as $plan) {
            DB::table('plan_feature')->updateOrInsert([
                'plan_id' => $plan->id,
                'feature' => Feature::AiAssist->value,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plan_feature')->where('feature', Feature::AiAssist->value)->delete();

        Schema::dropIfExists('shop_ai_settings');
    }
};

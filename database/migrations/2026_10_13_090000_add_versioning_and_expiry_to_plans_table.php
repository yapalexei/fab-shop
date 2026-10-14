<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan row becomes one *version* of a tier: editing a tier's price,
     * seats or features publishes a new row with the same `key` and marks
     * the old one superseded, so shops already on it keep what they signed
     * up for and the old row stays as the record. `key` is therefore no
     * longer unique on its own - (key, version) is, and a partial index
     * guarantees exactly one current (not superseded) row per key.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['key']);

            // Display/upgrade order, replacing the hardcoded Plan::TIER_ORDER.
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('superseded_at')->nullable();
            // Nullable: the date the tier leaves the public list. Existing
            // subscribers stay on it either way.
            $table->date('expires_on')->nullable();
            $table->boolean('show_expiry')->default(false);

            $table->unique(['key', 'version']);
        });

        DB::statement('create unique index plans_one_current_per_key on plans (key) where superseded_at is null');

        // Keep today's order for the four tiers that already exist; anything
        // else (none in practice) goes after them in creation order.
        $known = ['free' => 0, 'solo' => 1, 'start-up' => 2, 'pro' => 3];

        foreach ($known as $key => $position) {
            DB::table('plans')->where('key', $key)->update(['position' => $position]);
        }

        DB::table('plans')->whereNotIn('key', array_keys($known))->update(['position' => DB::raw('100 + id')]);
    }

    public function down(): void
    {
        DB::statement('drop index if exists plans_one_current_per_key');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['key', 'version']);
            $table->dropColumn(['position', 'version', 'superseded_at', 'expires_on', 'show_expiry']);
            $table->unique('key');
        });
    }
};

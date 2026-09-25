<?php

namespace App\Support;

use App\Enums\JobStageRole;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * The job board pipeline every shop starts with: the three fixed stages
 * (Not Started, Shipped, Invoiced - see JobStageRole) plus one generic
 * custom starter stage between them. Deliberately not named after any real
 * fabrication step (not "Cutting" or "Welding") - a shop's actual process
 * varies too much to presume (one shop cuts on a bandsaw, another on a CNC,
 * neither of which is "welding"), so the starter is just something to
 * rename/split/multiply from, same spirit as MaterialTypes::DEFAULTS being
 * a starting catalog rather than a mandate.
 */
final class JobStages
{
    public const STARTER_STAGE_NAME = 'Setup';

    /**
     * Idempotent - a shop that already has any stages is left alone, so this
     * is safe to call from Shop::created, demo seeders, and a migration
     * backfill alike without double-seeding.
     */
    public static function seedDefaults(Shop $shop): void
    {
        if (DB::table('job_stages')->where('shop_id', $shop->id)->exists()) {
            return;
        }

        $now = now();

        DB::table('job_stages')->insert([
            ['shop_id' => $shop->id, 'name' => 'Not Started', 'position' => 0, 'role' => JobStageRole::NotStarted->value, 'created_at' => $now, 'updated_at' => $now],
            ['shop_id' => $shop->id, 'name' => self::STARTER_STAGE_NAME, 'position' => 1, 'role' => null, 'created_at' => $now, 'updated_at' => $now],
            ['shop_id' => $shop->id, 'name' => 'Shipped', 'position' => 2, 'role' => JobStageRole::Shipped->value, 'created_at' => $now, 'updated_at' => $now],
            ['shop_id' => $shop->id, 'name' => 'Invoiced', 'position' => 3, 'role' => JobStageRole::Invoiced->value, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}

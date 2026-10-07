<?php

namespace App\Actions;

use App\Models\DataExport;
use App\Models\Shop;
use App\Models\User;
use App\Support\SecurityLog;
use App\Support\ShopData\ModelTables;
use App\Support\ShopData\TenantTables;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Permanently erases a shop and everything it owns: every table found by
 * TenantTables, the uploaded files, the shop's history entries, its AI agent
 * and finished exports. People's own accounts are NOT deleted - someone who
 * belonged to this shop may belong to others, and an account is theirs, not
 * the shop's; those left with no shop simply land in onboarding next time.
 *
 * Kept on purpose: the security log (including a "shop deleted" entry) and the
 * consent records, as the audit trail of what happened and what was agreed.
 *
 * Runs from the scheduler with no signed-in user, so every query names the
 * shop explicitly. Irreversible - callers decide when it is time.
 */
class PurgeShop
{
    public function __invoke(Shop $shop): void
    {
        $tables = TenantTables::forShop($shop->id);
        $name = $shop->name;
        $id = $shop->id;
        $owner = $shop->owner;

        $this->deleteFiles($shop, $tables);

        DB::transaction(function () use ($shop, $tables) {
            $this->deleteHistory($tables);
            $this->deleteRows($tables);
            $this->detachPeople($shop);
            $shop->delete();
        });

        SecurityLog::record('shop deleted', $owner instanceof User ? $owner : null, [
            'deleted_shop_id' => $id,
            'deleted_shop' => $name,
        ]);
    }

    /**
     * @param  array<string, array{where: string, depth: int}>  $tables
     */
    private function deleteFiles(Shop $shop, array $tables): void
    {
        // Spatie removes the stored file when the media row is deleted.
        foreach (ModelTables::withMedia() as $table => $class) {
            if (! isset($tables[$table])) {
                continue;
            }

            Media::query()
                ->where('model_type', (new $class)->getMorphClass())
                ->whereRaw('model_id IN ('.TenantTables::idsSubquery($table, $tables[$table]['where']).')')
                ->each(fn (Media $media) => $media->delete());
        }

        DataExport::withoutGlobalScopes()->where('shop_id', $shop->id)->whereNotNull('path')
            ->each(fn (DataExport $export) => Storage::delete($export->path));
    }

    /**
     * The "who changed what" entries for the shop's records point at them by
     * class name and id, with no foreign key, so they are matched by hand.
     *
     * @param  array<string, array{where: string, depth: int}>  $tables
     */
    private function deleteHistory(array $tables): void
    {
        foreach (ModelTables::all() as $table => $class) {
            if (! isset($tables[$table])) {
                continue;
            }

            Activity::query()
                ->where('subject_type', (new $class)->getMorphClass())
                ->whereRaw('subject_id IN ('.TenantTables::idsSubquery($table, $tables[$table]['where']).')')
                ->delete();
        }
    }

    /**
     * Children before parents (deepest first); a delete a foreign key still
     * blocks is retried after the others, each in its own savepoint so one
     * refusal doesn't abort the whole transaction.
     *
     * @param  array<string, array{where: string, depth: int}>  $tables
     */
    private function deleteRows(array $tables): void
    {
        uasort($tables, fn ($a, $b) => $b['depth'] <=> $a['depth']);
        $remaining = $tables;

        for ($pass = 0; $remaining !== [] && $pass <= count($tables); $pass++) {
            foreach ($remaining as $table => $info) {
                try {
                    DB::transaction(fn () => DB::table($table)->whereRaw($info['where'])->delete());
                    unset($remaining[$table]);
                } catch (QueryException $e) {
                    // foreign_key_violation / restrict_violation: a child table
                    // still points here; try again after the others.
                    if (! in_array($e->getCode(), ['23503', '23001'], true)) {
                        throw $e;
                    }
                }
            }
        }

        if ($remaining !== []) {
            throw new \RuntimeException('Could not erase: '.implode(', ', array_keys($remaining)));
        }
    }

    private function detachPeople(Shop $shop): void
    {
        foreach ($shop->agents()->get() as $agent) {
            $agent->tokens()->delete();
        }

        $agentIds = $shop->agents()->pluck('users.id');

        // People who had this as their current shop move to another one of
        // theirs, or to none (the app then sends them to onboarding).
        User::query()->where('current_shop_id', $shop->id)->each(function (User $user) use ($shop) {
            $user->current_shop_id = DB::table('shop_user')
                ->where('user_id', $user->id)->where('shop_id', '!=', $shop->id)
                ->orderBy('shop_id')->value('shop_id');
            $user->save();
        });

        DB::table('shop_user')->where('shop_id', $shop->id)->delete();
        User::query()->whereIn('id', $agentIds)->delete();
    }
}

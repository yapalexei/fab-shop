<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * Reads the security log (SecurityLog) for the two places it is shown: the
 * platform admin portal sees everything; a shop's owner sees only entries
 * that belong to their shop - their members' sign-ins and changes, plus what
 * platform staff did to the shop. Failed logins have no shop and stay
 * platform-only.
 */
class SecurityLogQuery
{
    private const PER_PAGE = 50;

    public static function forPlatform(Request $request): LengthAwarePaginator
    {
        return self::paginate(self::base(), $request);
    }

    public static function forShop(Shop $shop, Request $request): LengthAwarePaginator
    {
        $id = (string) $shop->id;

        $query = self::base()->where(function (Builder $q) use ($id) {
            // Platform staff's own sign-ins and actions carry *their* current
            // shop id, so only the entries that name this shop as the target
            // count as "done to it".
            $q->where(fn (Builder $own) => $own
                ->where('properties->shop_id', $id)
                ->where('description', 'not like', 'platform admin:%'))
                ->orWhere('properties->target_shop_id', $id);
        });

        return self::paginate($query, $request);
    }

    /**
     * Event names present in the log, for the filter dropdown.
     *
     * @return list<string>
     */
    public static function events(?Shop $shop = null): array
    {
        $query = Activity::query()->where('log_name', SecurityLog::LOG);

        if ($shop) {
            $id = (string) $shop->id;
            $query->where(fn ($q) => $q
                ->where(fn ($own) => $own->where('properties->shop_id', $id)->where('description', 'not like', 'platform admin:%'))
                ->orWhere('properties->target_shop_id', $id));
        }

        return $query->distinct()->orderBy('description')->limit(100)->pluck('description')->all();
    }

    private static function base(): Builder
    {
        return Activity::query()->where('log_name', SecurityLog::LOG)->with('causer')->latest('id');
    }

    private static function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        if ($event = $request->string('event')->trim()->value()) {
            $query->where('description', $event);
        }

        if ($term = $request->string('q')->trim()->value()) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

            $query->where(fn (Builder $q) => $q
                ->whereHasMorph('causer', [User::class], fn ($u) => $u->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like))
                ->orWhereRaw("properties->>'email' ilike ?", [$like])
                ->orWhereRaw("properties->>'ip' ilike ?", [$like]));
        }

        return $query->paginate(self::PER_PAGE)->withQueryString()->through(fn (Activity $entry) => [
            'id' => $entry->id,
            'at' => $entry->created_at->toIso8601String(),
            'event' => $entry->description,
            'user' => $entry->causer instanceof User ? ['name' => $entry->causer->name, 'email' => $entry->causer->email] : null,
            'ip' => $entry->properties['ip'] ?? null,
            'details' => collect($entry->properties->toArray())
                ->except(['ip', 'user_agent', 'shop_id'])
                ->map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value))
                ->all(),
        ]);
    }
}

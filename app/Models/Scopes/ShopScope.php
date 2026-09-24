<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts every query on a tenant-owned model to the authenticated user's
 * *current* shop — not every shop they're a member of. Switching shops changes
 * what this scope sees, without touching shop_user membership.
 *
 * Only applies when there's an authenticated user, so console commands, queued
 * jobs, and seeders are unaffected — those contexts must filter by shop_id
 * explicitly since there's no session to scope from.
 */
class ShopScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $builder->where($model->qualifyColumn('shop_id'), Auth::user()->current_shop_id);
        }
    }
}

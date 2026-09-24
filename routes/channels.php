<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * The job board listens here for "something changed, refetch" pings -
 * scoped to the viewer's *current* shop, matching every other tenant
 * boundary in the app.
 */
Broadcast::channel('shop.{shopId}.jobs', function (User $user, int $shopId) {
    return (int) $user->current_shop_id === $shopId;
});

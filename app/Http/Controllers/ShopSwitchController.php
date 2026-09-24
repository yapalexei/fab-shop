<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopSwitchController extends Controller
{
    public function update(Request $request, Shop $shop): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->shops()->where('shops.id', $shop->id)->exists(), 403);

        // current_shop_id is deliberately not fillable (see User model) - set
        // it directly rather than through update(), same as everywhere else
        // this field is touched.
        $user->current_shop_id = $shop->id;
        $user->save();

        return back()->with('status', "Switched to {$shop->name}.");
    }
}

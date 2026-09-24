<?php

namespace App\Http\Middleware;

use App\Enums\ShopPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shop = $request->user()?->currentShop;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'can' => $request->user() ? [
                    'manageShop' => Gate::allows('manage-shop'),
                    'viewFinancials' => Gate::allows(ShopPermission::ViewFinancials->value),
                    'manageShopSettings' => Gate::allows(ShopPermission::ManageShopSettings->value),
                ] : null,
            ],
            'shop' => $shop ? [
                'id' => $shop->id,
                'name' => $shop->name,
                'timezone' => $shop->localTimezone(),
            ] : null,
            'enabledFeatures' => $shop
                ? $shop->enabledFeatures()->map(fn ($feature) => $feature->value)->values()
                : [],
            'availableShops' => $request->user()
                ? $request->user()->shops()->orderBy('name')->get(['shops.id', 'shops.name'])
                : [],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}

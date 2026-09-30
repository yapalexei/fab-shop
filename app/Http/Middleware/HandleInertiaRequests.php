<?php

namespace App\Http\Middleware;

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
                // Effective shop permissions ("quotes.delete", ...) so the UI
                // can hide what the server would refuse anyway.
                'permissions' => $request->user()?->shopPermissions() ?? [],
                'isOwner' => $request->user() ? Gate::allows('manage-shop') : false,
                'isPlatformAdmin' => $request->user()?->isPlatformAdmin() ?? false,
            ],
            'shop' => $shop ? [
                'id' => $shop->id,
                'name' => $shop->name,
                'timezone' => $shop->localTimezone(),
                'job_board_drag_enabled' => $shop->jobBoardDragEnabled(),
                'on_trial' => $shop->onTrial(),
                'trial_ends_at' => $shop->trial_ends_at?->toIso8601String(),
                'plan_name' => $shop->plan?->name,
                // Only the owner can change the plan, so only they get the button.
                'can_upgrade' => Gate::allows('manage-shop') && $shop->canUpgrade(),
            ] : null,
            // Features the shop can look at but not use (trial ended / not in plan).
            'readOnlyFeatures' => $shop
                ? $shop->readOnlyFeatures()->map(fn ($feature) => $feature->value)->values()
                : [],
            'enabledFeatures' => $shop
                ? $shop->enabledFeatures()->map(fn ($feature) => $feature->value)->values()
                : [],
            'availableShops' => $request->user()
                ? $request->user()->shops()->orderBy('name')->get(['shops.id', 'shops.name'])
                : [],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

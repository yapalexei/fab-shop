<?php

namespace App\Http\Middleware;

use App\Support\PublicSsr;
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
                'user' => $request->user()
                    ? [...$request->user()->toArray(), 'avatar_url' => $request->user()->avatarUrl()]
                    : null,
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
                'show_trial_ended_notice' => $request->user() ? $shop->showsTrialEndedNoticeTo($request->user()) : false,
                'plan_name' => $shop->plan?->name,
                'deletion_scheduled_for' => $shop->deletion_scheduled_for?->toIso8601String(),
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
            // Set by EnsureTwoFactorSetup: present only while the signed-in
            // person still owes two-factor setup (before the deadline - after
            // it they're held on the profile page instead).
            'twoFactorNotice' => ($requirement = $request->attributes->get('two_factor_requirement'))
                ? [
                    'deadline' => $requirement['deadline']->toIso8601String(),
                    'days_left' => $requirement['days_left'],
                    'overdue' => $requirement['overdue'],
                ]
                : null,
            // Route table for the SSR service (it has no window.Ziggy) - only
            // on the first load of a server-rendered page, never otherwise.
            'ziggy' => PublicSsr::appliesTo($request) ? PublicSsr::ziggy(...) : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyTwoFactorRequirement;
use App\Support\SecurityLog;
use App\Support\SecurityLogQuery;
use App\Support\TwoFactorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's view of their shop's security: the two-factor requirement
 * (follow the platform default or set their own) and the security log for
 * their shop. The policy form itself is rendered on the shop settings page.
 */
class ShopSecurityController extends Controller
{
    /**
     * The Security index: links to its sub-pages (roles & permissions, the
     * audit log) and to where the two-factor requirement is set.
     */
    public function index(): Response
    {
        return Inertia::render('ShopSettings/Security');
    }

    /**
     * Who must use two-factor sign-in in this shop: follow the platform
     * default or set the shop's own.
     */
    public function twoFactor(): Response
    {
        $own = TwoFactorPolicy::forShop(Auth::user()->currentShop);
        $platform = TwoFactorPolicy::platform();

        return Inertia::render('ShopSettings/SecurityTwoFactor', [
            'twoFactor' => [
                'follows_default' => $own['source'] === 'platform',
                'values' => ['roles' => $own['roles'], 'grace_days' => $own['grace_days']],
                'platform_default' => ['roles' => $platform['roles'], 'grace_days' => $platform['grace_days']],
                'role_options' => TwoFactorPolicy::ROLES,
                'max_grace_days' => TwoFactorPolicy::MAX_GRACE_DAYS,
            ],
        ]);
    }

    public function log(Request $request): Response
    {
        $shop = Auth::user()->currentShop;

        return Inertia::render('ShopSettings/SecurityLog', [
            'log' => SecurityLogQuery::forShop($shop, $request),
            'events' => SecurityLogQuery::events($shop),
            'filters' => $request->only('event', 'q'),
        ]);
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;

        $data = $request->validate([
            'mode' => ['required', Rule::in(['inherit', 'custom'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(TwoFactorPolicy::ROLES)],
            'grace_days' => ['nullable', 'integer', 'min:1', 'max:'.TwoFactorPolicy::MAX_GRACE_DAYS],
        ]);

        $before = TwoFactorPolicy::forShop($shop);

        if ($data['mode'] === 'inherit') {
            $shop->forceFill([
                'two_factor_required_roles' => null,
                'two_factor_grace_days' => null,
                'two_factor_policy_set_at' => null,
            ])->save();
        } else {
            $roles = TwoFactorPolicy::cleanRoles($data['roles'] ?? []);
            $grace = $data['grace_days'] ?? TwoFactorPolicy::platform()['grace_days'];
            $same = $before['source'] === 'shop' && $before['roles'] === $roles && $before['grace_days'] === $grace;

            // Re-saving the same values must not restart anyone's countdown.
            $shop->forceFill([
                'two_factor_required_roles' => $roles,
                'two_factor_grace_days' => $grace,
                'two_factor_policy_set_at' => $same ? $shop->two_factor_policy_set_at : now(),
            ])->save();
        }

        $after = TwoFactorPolicy::forShop($shop->refresh());

        if ($after['roles'] !== $before['roles'] || $after['grace_days'] !== $before['grace_days'] || $after['set_at'] != $before['set_at']) {
            SecurityLog::record('two-factor requirement changed', $request->user(), [
                'follows_platform_default' => $after['source'] === 'platform',
                'roles' => $after['roles'],
                'grace_days' => $after['grace_days'],
            ]);

            if ($after['roles'] !== []) {
                NotifyTwoFactorRequirement::dispatch($shop->id);
            }
        }

        return back()->with('status', 'Two-factor requirement saved.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyTwoFactorRequirement;
use App\Models\PlatformSetting;
use App\Support\SecurityLog;
use App\Support\SecurityLogQuery;
use App\Support\TwoFactorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admin > Security: the default two-factor requirement for every
 * shop (a shop's owner can follow it or override it) and the security log
 * across all shops.
 */
class PlatformSecurityController extends Controller
{
    /**
     * The Security index: links to its sub-pages.
     */
    public function index(): Response
    {
        return Inertia::render('PlatformAdmin/Security');
    }

    public function policy(): Response
    {
        $policy = TwoFactorPolicy::platform();

        return Inertia::render('PlatformAdmin/SecurityPolicy', [
            'policy' => [
                'roles' => $policy['roles'],
                'grace_days' => $policy['grace_days'],
                'set_at' => $policy['set_at']?->toIso8601String(),
            ],
            'roleOptions' => TwoFactorPolicy::ROLES,
            'maxGraceDays' => TwoFactorPolicy::MAX_GRACE_DAYS,
        ]);
    }

    public function log(Request $request): Response
    {
        return Inertia::render('PlatformAdmin/SecurityLog', [
            'log' => SecurityLogQuery::forPlatform($request),
            'events' => SecurityLogQuery::events(),
            'filters' => $request->only('event', 'q'),
        ]);
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(TwoFactorPolicy::ROLES)],
            'grace_days' => ['required', 'integer', 'min:1', 'max:'.TwoFactorPolicy::MAX_GRACE_DAYS],
        ]);

        $before = TwoFactorPolicy::platform();
        $roles = TwoFactorPolicy::cleanRoles($data['roles']);
        $changed = $roles !== $before['roles'] || $data['grace_days'] !== $before['grace_days'];

        if (! $changed) {
            return back()->with('status', 'No change.');
        }

        // Written with forceFill: the policy and its set-at timestamp (which
        // starts everyone's countdown) are only ever changed together, here.
        PlatformSetting::current()->forceFill([
            'two_factor_required_roles' => $roles,
            'two_factor_grace_days' => $data['grace_days'],
            'two_factor_policy_set_at' => now(),
        ])->save();

        SecurityLog::record('platform admin: two-factor default changed', $request->user(), [
            'roles' => $roles,
            'grace_days' => $data['grace_days'],
        ]);

        if ($roles !== []) {
            NotifyTwoFactorRequirement::dispatch(null);
        }

        return back()->with('status', $roles === []
            ? 'Two-factor is no longer required by default.'
            : 'Saved. Everyone newly covered has been emailed.');
    }
}

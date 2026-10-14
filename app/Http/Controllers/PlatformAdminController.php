<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtendShopSubscriptionRequest;
use App\Http\Requests\ExtendShopTrialRequest;
use App\Http\Requests\UpdateShopPlanRequest;
use App\Http\Requests\UpdateShopSeatLimitRequest;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Shop;
use App\Support\SecurityLog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal, cross-tenant tool for platform staff (App\Models\User::$is_platform_admin,
 * gated by the platform-admin Gate) - not a per-shop role, deliberately able
 * to see and act across every shop, which is why every query here reads
 * every shop rather than the current one.
 *
 * Revenue shown is potential/theoretical only (plan price x shop counts) -
 * not tied to Stripe, Square or any real collected-payment data, which is
 * explicitly deferred (see docs/product-desc.md's F10 section).
 */
class PlatformAdminController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $shops = $this->filtered($request)
            ->with(['plan', 'owner'])
            ->withCount('members')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'owner_email' => $shop->owner->email,
                'plan_id' => $shop->plan_id,
                'plan_name' => $shop->plan?->name,
                'on_trial' => $shop->onTrial(),
                'trial_ends_at' => $shop->trial_ends_at,
                'is_onboarded' => $shop->isOnboarded(),
                'is_suspended' => $shop->isSuspended(),
                'member_count' => $shop->members_count,
                'seat_limit' => $shop->seatLimit(),
                'seat_limit_override' => $shop->seat_limit_override,
                'subscription_renews_at' => $shop->subscription_renews_at,
                'subscription_overdue' => $shop->isSubscriptionOverdue(),
                'created_at' => $shop->created_at,
            ]);

        return Inertia::render('PlatformAdmin/Index', [
            'shops' => $shops,
            'filters' => $request->only(['search', 'status', 'plan_id', 'trial_within_days', 'subscription']),
            'plans' => $this->planOptions(),
            // Platform-wide, deliberately independent of the search/filter/
            // pagination above - a filtered view narrowing the table to 3
            // shops shouldn't make the revenue cards look like the platform
            // only has 3 shops.
            'revenue' => $this->revenueSummary(Shop::with('plan')->get()),
        ]);
    }

    /**
     * Search matches shop name or owner email. Status mirrors the exact
     * precedence the table's own badge uses (suspended > onboarding > trial
     * > active), so filtering by "Trial" never disagrees with what the
     * Status column actually shows for a row. trial_within_days catches
     * both "ending soon" and already-lapsed trials (<=, not a range) - the
     * useful question for a platform admin is "which of these need my
     * attention soon," not a precise window.
     */
    /**
     * Platform staff act across tenants, so every change they make to a shop
     * goes in the security log with the shop it touched (their own current
     * shop, which SecurityLog records, is usually a different one).
     *
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $what, Shop $shop, array $properties = []): void
    {
        SecurityLog::record("platform admin: {$what}", Auth::user(), [
            ...$properties,
            'target_shop_id' => $shop->id,
            'target_shop' => $shop->name,
        ]);
    }

    /**
     * Every plan an admin can filter by or move a shop onto: what's on offer,
     * plus any older or removed version a shop is still on (labelled, so a
     * grandfathered shop's plan shows up correctly instead of as blank).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function planOptions(): Collection
    {
        return Plan::query()
            ->offered()
            ->orWhereHas('shops')
            ->orderBy('position')->orderBy('key')->orderByDesc('version')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => match (true) {
                    $plan->isOffered() => $plan->name,
                    $plan->isCurrent() => "{$plan->name} (removed from public list)",
                    default => "{$plan->name} (v{$plan->version}, archived)",
                },
            ]);
    }

    private function filtered(Request $request): Builder
    {
        $query = Shop::query();

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('owner', fn (Builder $q) => $q->where('email', 'ilike', "%{$search}%"));
            });
        }

        match ($request->string('status')->value()) {
            'suspended' => $query->whereNotNull('suspended_at'),
            'onboarding' => $query->whereNull('suspended_at')->whereNull('onboarded_at'),
            'trial' => $query->whereNull('suspended_at')->whereNotNull('onboarded_at')
                ->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now()),
            'active' => $query->whereNull('suspended_at')->whereNotNull('onboarded_at')
                ->where(fn (Builder $q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now())),
            default => null,
        };

        if ($request->filled('plan_id')) {
            $request->input('plan_id') === 'none'
                ? $query->whereNull('plan_id')
                : $query->where('plan_id', $request->input('plan_id'));
        }

        if ($request->filled('trial_within_days')) {
            $query->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<=', now()->addDays((int) $request->input('trial_within_days')));
        }

        match ($request->string('subscription')->value()) {
            'overdue' => $query->whereNotNull('subscription_renews_at')->where('subscription_renews_at', '<=', now()),
            'paid' => $query->whereNotNull('subscription_renews_at')->where('subscription_renews_at', '>', now()),
            'not_tracked' => $query->whereNull('subscription_renews_at'),
            default => null,
        };

        return $query;
    }

    public function suspend(Shop $shop): RedirectResponse
    {
        $shop->forceFill(['suspended_at' => now()])->save();
        $this->audit('shop suspended', $shop);

        return back()->with('status', "Suspended {$shop->name}.");
    }

    public function unsuspend(Shop $shop): RedirectResponse
    {
        $shop->forceFill(['suspended_at' => null])->save();
        $this->audit('shop unsuspended', $shop);

        return back()->with('status', "Unsuspended {$shop->name}.");
    }

    /**
     * Adds days on top of whatever's left, not from trial_started_at - so
     * extending twice stacks rather than the second call clobbering the
     * first. If the trial already lapsed (or the shop never had one),
     * extends from now instead of from a stale past date, so "extend by 7"
     * always means "7 more days from today," not "7 days past whenever it
     * used to end."
     */
    public function extendTrial(ExtendShopTrialRequest $request, Shop $shop): RedirectResponse
    {
        $days = $request->validated('days');
        $base = $shop->trial_ends_at?->isFuture() ? $shop->trial_ends_at : now();

        $shop->update([
            'trial_started_at' => $shop->trial_started_at ?? now(),
            'trial_baseline_days' => $shop->trial_baseline_days ?? PlatformSetting::defaultTrialDays(),
            'trial_ends_at' => $base->copy()->addDays($days),
        ]);
        $this->audit('trial extended', $shop, ['days' => $days]);

        return back()->with('status', "Extended {$shop->name}'s trial by {$days} day(s), to {$shop->trial_ends_at->toDateString()}.");
    }

    /**
     * Ends the trial as of right now rather than nulling trial_ends_at out -
     * a feature configured with its own longer trial_days
     * (Shop::onTrialFor()) should still get to run out its own window, the
     * same way a platform admin extending trial_ends_at already shifts
     * every feature's window with it.
     */
    public function expireTrial(Shop $shop): RedirectResponse
    {
        $shop->update(['trial_ends_at' => now()->subSecond()]);
        $this->audit('trial ended', $shop);

        return back()->with('status', "Ended {$shop->name}'s trial.");
    }

    /**
     * The manual "mark this shop paid" action - stacks on top of whatever's
     * left the same way extendTrial() does, so recording a renewal early
     * doesn't lose the days already paid for.
     */
    public function extendSubscription(ExtendShopSubscriptionRequest $request, Shop $shop): RedirectResponse
    {
        $days = $request->validated('days');
        $base = $shop->subscription_renews_at?->isFuture() ? $shop->subscription_renews_at : now();

        $shop->update(['subscription_renews_at' => $base->copy()->addDays($days)]);
        $this->audit('subscription extended', $shop, ['days' => $days]);

        return back()->with('status', "Marked {$shop->name} paid through {$shop->subscription_renews_at->toDateString()}.");
    }

    public function updatePlan(UpdateShopPlanRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update(['plan_id' => $request->validated('plan_id')]);
        $this->audit('plan changed', $shop, ['plan_id' => $request->validated('plan_id')]);

        // A feature the shop was individually paying for as an add-on may
        // now be included in the new plan - cancel it so it's never billed
        // twice for the same thing.
        $cancelled = $shop->cancelSubscriptionsBundledInPlan();

        $status = "Updated {$shop->name}'s plan.";
        if ($cancelled > 0) {
            $status .= " Cancelled {$cancelled} add-on subscription(s) now included in the new plan.";
        }

        return back()->with('status', $status);
    }

    /**
     * Null clears the override back to whatever the shop's own plan grants -
     * not a way to set "unlimited" specifically (Plan::user_limit already
     * means that when null), just "stop overriding."
     */
    public function updateSeatLimit(UpdateShopSeatLimitRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update(['seat_limit_override' => $request->validated('seat_limit')]);
        $this->audit('seat limit changed', $shop, ['seat_limit' => $request->validated('seat_limit')]);

        return back()->with('status', "Updated {$shop->name}'s seat limit.");
    }

    /**
     * Two numbers, both theoretical: what active (post-trial, unsuspended)
     * shops are nominally paying now, and what's sitting in trials if they
     * all convert. Neither is real collected revenue - see the class doc.
     *
     * @param  Collection<int, Shop>  $shops
     * @return array<string, int>
     */
    private function revenueSummary(Collection $shops): array
    {
        $billable = $shops->filter(fn (Shop $shop) => ! $shop->isSuspended());

        $activeMrrCents = $billable
            ->filter(fn (Shop $shop) => ! $shop->onTrial())
            ->sum(fn (Shop $shop) => $shop->plan?->monthly_price_cents ?? 0);

        $trialPotentialMrrCents = $billable
            ->filter(fn (Shop $shop) => $shop->onTrial())
            ->sum(fn (Shop $shop) => $shop->plan?->monthly_price_cents ?? 0);

        return [
            'active_mrr_cents' => (int) $activeMrrCents,
            'trial_potential_mrr_cents' => (int) $trialPotentialMrrCents,
        ];
    }
}

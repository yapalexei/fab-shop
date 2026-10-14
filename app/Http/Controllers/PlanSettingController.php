<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Requests\SavePlanRequest;
use App\Models\Plan;
use App\Models\Shop;
use App\Support\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-admin control over the subscription tiers themselves - how many
 * there are, what each costs, how many seats and which features it bundles,
 * and when it leaves the public list - gated the same way as the rest of the
 * platform admin console (can:platform-admin), so none of it is reachable
 * from a shop's own pages. Distinct from FeatureSettingController, which
 * prices a feature as a standalone add-on and sets the default trial length,
 * and from PlatformAdminController's per-shop plan/seat overrides.
 *
 * Tiers are never edited in place or deleted: see Plan::applyEdit(). A save
 * that changes what a subscriber is entitled to publishes a new version and
 * archives the old one, which keeps its price, seats and features for the
 * shops still on it - the history shown here is those archived versions and
 * how many shops remain on each.
 *
 * The Free tier is the fallback a shop lands on when its trial ends, so it
 * is editable like the others but can't be given an expiry date.
 */
class PlanSettingController extends Controller
{
    public function index(): Response
    {
        $versions = Plan::query()->orderByDesc('version')->get();
        $subscribers = Shop::query()->selectRaw('plan_id, count(*) as total')->whereNotNull('plan_id')->groupBy('plan_id')->pluck('total', 'plan_id');

        $tiers = $versions->filter(fn (Plan $plan) => $plan->isCurrent())->sortBy([['position', 'asc'], ['id', 'asc']])->values();

        return Inertia::render('PlatformAdmin/Plans', [
            'tiers' => $tiers->map(fn (Plan $plan) => [
                ...$this->row($plan, $subscribers),
                'protected' => $plan->key === Plan::FALLBACK_KEY,
                'status' => match (true) {
                    $plan->isOffered() && $plan->expires_on !== null => 'expiring',
                    $plan->isOffered() => 'offered',
                    default => 'removed',
                },
                'history' => $versions
                    ->filter(fn (Plan $old) => $old->key === $plan->key && ! $old->isCurrent())
                    ->map(fn (Plan $old) => $this->row($old, $subscribers))
                    ->values(),
            ]),
            'allFeatures' => collect(Feature::cases())->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'shipped' => $feature->isShipped(),
            ]),
        ]);
    }

    public function store(SavePlanRequest $request): RedirectResponse
    {
        $plan = Plan::createTier($this->attributes($request), $this->features($request));

        SecurityLog::record('platform admin: plan tier created', Auth::user(), [
            'plan_key' => $plan->key,
            'monthly_price_cents' => $plan->monthly_price_cents,
        ]);

        return redirect()->route('platform-admin.plans.index')->with('status', "Added the {$plan->name} tier.");
    }

    public function update(SavePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->ensureEditable($plan);

        $attributes = $this->attributes($request);

        if ($plan->key === Plan::FALLBACK_KEY && $attributes['expires_on'] !== null) {
            throw ValidationException::withMessages(['expires_on' => 'The Free tier is where shops land when a trial ends, so it can\'t be given an end date.']);
        }

        $before = $plan->version;
        $current = $plan->applyEdit($attributes, $this->features($request));
        $versioned = $current->version !== $before;

        SecurityLog::record('platform admin: plan tier changed', Auth::user(), [
            'plan_key' => $plan->key,
            'version' => $current->version,
            'new_version' => $versioned,
            'monthly_price_cents' => $current->monthly_price_cents,
        ]);

        return redirect()->route('platform-admin.plans.index')->with('status', $versioned
            ? "Published version {$current->version} of {$current->name}. Shops already on the previous version keep it."
            : "Updated {$current->name}.");
    }

    /** Take a tier off the public list now. Shops on it keep it. */
    public function retire(Plan $plan): RedirectResponse
    {
        $this->ensureEditable($plan);

        if ($plan->key === Plan::FALLBACK_KEY) {
            throw ValidationException::withMessages(['plan' => 'The Free tier is where shops land when a trial ends, so it can\'t be removed.']);
        }

        $plan->retire();

        SecurityLog::record('platform admin: plan tier removed from public list', Auth::user(), ['plan_key' => $plan->key]);

        return redirect()->route('platform-admin.plans.index')->with('status', "{$plan->name} is no longer offered. Shops already on it keep it.");
    }

    /**
     * Only the current version of a tier can be edited - an archived one is a
     * record, and the shops on it must keep exactly what it says.
     */
    private function ensureEditable(Plan $plan): void
    {
        if (! $plan->isCurrent()) {
            throw ValidationException::withMessages(['plan' => 'That version is archived. Edit the tier\'s current version instead.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SavePlanRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => trim($data['name']),
            'monthly_price_cents' => (int) $data['monthly_price_cents'],
            'user_limit' => isset($data['user_limit']) ? (int) $data['user_limit'] : null,
            'extra_seat_price_cents' => isset($data['extra_seat_price_cents']) ? (int) $data['extra_seat_price_cents'] : null,
            'position' => isset($data['position']) ? (int) $data['position'] : null,
            'expires_on' => $data['expires_on'] ?? null,
            'show_expiry' => (bool) ($data['show_expiry'] ?? false),
        ];
    }

    /**
     * @return array<int, Feature>
     */
    private function features(SavePlanRequest $request): array
    {
        return collect($request->validated('features') ?? [])
            ->map(fn (string $feature) => Feature::from($feature))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int|string, mixed>  $subscribers  shops per plan id
     * @return array<string, mixed>
     */
    private function row(Plan $plan, Collection $subscribers): array
    {
        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'version' => $plan->version,
            'position' => $plan->position,
            'monthly_price_cents' => $plan->monthly_price_cents,
            'extra_seat_price_cents' => $plan->extra_seat_price_cents,
            'user_limit' => $plan->user_limit,
            'features' => $plan->features()->map(fn (Feature $feature) => $feature->value)->values(),
            'expires_on' => $plan->expires_on?->toDateString(),
            'show_expiry' => $plan->show_expiry,
            'published_at' => $plan->created_at?->toIso8601String(),
            'archived_at' => $plan->superseded_at?->toIso8601String(),
            'subscribers' => (int) ($subscribers[$plan->id] ?? 0),
        ];
    }
}

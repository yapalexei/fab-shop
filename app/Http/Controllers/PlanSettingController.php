<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Requests\UpdatePlanSettingsRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-admin control over each subscription tier's own definition -
 * default seats (Plan::user_limit) and which features it bundles
 * (plan_feature, via Plan::syncFeatures()) - gated the same way as the rest
 * of the platform admin console (can:platform-admin). Distinct from
 * FeatureSettingController, which prices a feature as a standalone add-on
 * and sets the platform-wide default trial length, and from
 * PlatformAdminController's per-shop plan/seat overrides, which apply on
 * top of whatever's set here for one specific shop.
 *
 * The Free plan is included like any other, but its bundled features here
 * are close to irrelevant in practice: a shop on Free is either still
 * inside its trial (Shop::onTrial() grants every feature regardless of
 * plan) or past it (and gets nothing further from Free either way) - see
 * PlanSeeder's own note and the marketing page's special-cased rendering of
 * the Free tier.
 */
class PlanSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('PlatformAdmin/Plans', [
            'plans' => Plan::orderedByTier()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'key' => $plan->key,
                'name' => $plan->name,
                'user_limit' => $plan->user_limit,
                'features' => $plan->features()->map(fn (Feature $feature) => $feature->value)->values(),
            ]),
            'allFeatures' => collect(Feature::cases())->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'shipped' => $feature->isShipped(),
            ]),
        ]);
    }

    public function update(UpdatePlanSettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated('plans') as $row) {
            $plan = Plan::query()->findOrFail($row['id']);

            $plan->update(['user_limit' => $row['user_limit']]);

            $plan->syncFeatures(collect($row['features'] ?? [])
                ->map(fn (string $feature) => Feature::from($feature))
                ->all());
        }

        return redirect()->route('platform-admin.plans.index')->with('status', 'Plan settings updated.');
    }
}

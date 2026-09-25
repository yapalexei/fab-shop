<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Requests\UpdateFeatureSettingsRequest;
use App\Models\FeatureSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-admin control over what each Feature costs as a standalone
 * add-on and how many free trial days it gets - gated the same way as
 * PlatformAdminController (can:platform-admin), not a per-shop concern.
 */
class FeatureSettingController extends Controller
{
    public function index(): Response
    {
        FeatureSetting::ensureDefaults();

        $settings = FeatureSetting::query()->get()->keyBy('feature');

        return Inertia::render('PlatformAdmin/Features', [
            'settings' => collect(Feature::cases())->map(fn (Feature $feature) => [
                'feature' => $feature->value,
                'label' => $feature->label(),
                'price_cents' => $settings[$feature->value]->price_cents,
                'trial_days' => $settings[$feature->value]->trial_days,
            ]),
        ]);
    }

    public function update(UpdateFeatureSettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated('settings') as $row) {
            FeatureSetting::query()->updateOrCreate(
                ['feature' => $row['feature']],
                ['price_cents' => $row['price_cents'], 'trial_days' => $row['trial_days']],
            );
        }

        return redirect()->route('platform-admin.features.index')->with('status', 'Feature pricing updated.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Requests\UpdateFeatureSettingsRequest;
use App\Models\FeatureSetting;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-admin control over what each Feature costs as a standalone
 * add-on, plus the platform-wide default trial length new shops (and the
 * marketing page) get - gated the same way as PlatformAdminController
 * (can:platform-admin), not a per-shop concern.
 *
 * Per-feature trial length (feature_settings.trial_days) is deliberately
 * not editable here any more - the mechanism it once drove (a feature
 * running a longer/shorter trial than the shop's overall one,
 * Shop::onTrialFor()) has no visible surface anywhere in the product, so
 * exposing an admin control for it was a lever that appeared to do
 * something but couldn't be seen or felt by a shop user. The column and
 * FeatureSetting::trialDaysFor()/ensureDefaults() are untouched - they keep
 * seeding and reading it, falling back to PlatformSetting::defaultTrialDays()
 * - only the ability to diverge a feature from that default is gone.
 */
class FeatureSettingController extends Controller
{
    public function index(): Response
    {
        FeatureSetting::ensureDefaults();

        $settings = FeatureSetting::query()->get()->keyBy('feature');

        return Inertia::render('PlatformAdmin/Features', [
            'defaultTrialDays' => PlatformSetting::defaultTrialDays(),
            'settings' => collect(Feature::cases())->map(fn (Feature $feature) => [
                'feature' => $feature->value,
                'label' => $feature->label(),
                'price_cents' => $settings[$feature->value]->price_cents,
            ]),
        ]);
    }

    public function update(UpdateFeatureSettingsRequest $request): RedirectResponse
    {
        PlatformSetting::current()->update([
            'default_trial_days' => $request->validated('default_trial_days'),
        ]);
        // So the marketing page and the next signup see the new value
        // immediately - PlatformSetting::defaultTrialDays() caches it
        // indefinitely otherwise.
        PlatformSetting::forgetCache();

        foreach ($request->validated('settings') as $row) {
            FeatureSetting::query()->updateOrCreate(
                ['feature' => $row['feature']],
                ['price_cents' => $row['price_cents']],
            );
        }

        return redirect()->route('platform-admin.features.index')->with('status', 'Feature pricing updated.');
    }
}

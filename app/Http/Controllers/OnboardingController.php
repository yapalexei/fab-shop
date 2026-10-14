<?php

namespace App\Http\Controllers;

use App\Actions\SendShopInvitation;
use App\Models\BusinessPartner;
use App\Models\Invitation;
use App\Models\Material;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\Shop;
use App\Support\ShopPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * The one-time wizard a verified user with no onboarded shop is routed
 * through by the `onboarded` middleware (see EnsureShopIsOnboarded). Each
 * step persists immediately - shops.onboarding_step tracks which one comes
 * next - so refreshing mid-wizard resumes rather than loses progress, and
 * a skipped step is just a submission with nothing in it.
 */
class OnboardingController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $shop = Auth::user()->currentShop;

        if ($shop?->isOnboarded()) {
            return redirect()->route('dashboard');
        }

        if (! $shop) {
            return Inertia::render('Onboarding/ShopStep', [
                'plans' => Plan::orderedByTier()->map(fn (Plan $plan) => [
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'user_limit' => $plan->user_limit,
                    'available_until' => $plan->show_expiry ? $plan->expires_on?->toDateString() : null,
                ]),
                'timezones' => \DateTimeZone::listIdentifiers(),
            ]);
        }

        return match ($shop->onboarding_step) {
            'team' => Inertia::render('Onboarding/TeamStep', [
                'shopName' => $shop->name,
                'roles' => ShopPermissions::EDITABLE_ROLES,
                'invited' => Invitation::where('shop_id', $shop->id)
                    ->whereNull('accepted_at')
                    ->get(['email', 'role']),
            ]),
            'catalog' => Inertia::render('Onboarding/CatalogStep', [
                'shopName' => $shop->name,
            ]),
            // A shop with no onboarding_step left is either already onboarded
            // (handled above) or in an unreachable state - safest default.
            default => redirect()->route('dashboard'),
        };
    }

    /**
     * Step 1: the shop itself. Creates it on first submission; if a shop
     * already exists but isn't onboarded (e.g. a duplicate submit), updates
     * that one in place instead of creating a second - but never touches
     * its trial dates once started.
     */
    public function storeShop(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'],
            'plan' => ['required', Rule::in(Plan::query()->offered()->pluck('key'))],
        ]);

        $user = Auth::user();
        $shop = $user->currentShop;
        $planId = Plan::offeredByKey($validated['plan'])->id;
        $timezone = $validated['timezone'] ?? null;

        if ($shop && ! $shop->isOnboarded()) {
            $shop->update([
                'name' => $validated['name'],
                'timezone' => $timezone,
                'plan_id' => $planId,
            ]);
        } else {
            $trialDays = PlatformSetting::defaultTrialDays();

            $shop = Shop::create([
                'name' => $validated['name'],
                'owner_id' => $user->id,
                'plan_id' => $planId,
                'timezone' => $timezone,
                // Fixed from this moment, in UTC - see Shop::TRIAL_DAYS'
                // own note on why this never derives from the timezone.
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays($trialDays),
                // Captured once, permanently - see Shop::trialBaselineDays()
                // for why this shop's own per-feature trial math must keep
                // using today's default rather than whatever it becomes
                // later if a platform admin changes it.
                'trial_baseline_days' => $trialDays,
                'onboarding_step' => 'team',
            ]);

            $shop->members()->attach($user);
            $user->current_shop_id = $shop->id;
            $user->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
            ShopPermissions::seedRoles($shop);
            $user->assignRole('Owner');
        }

        return redirect()->route('onboarding.show');
    }

    /**
     * Step 2 (skippable): invite teammates by email. An empty list is a
     * skip - there's nothing else that distinguishes "skipped" from
     * "invited nobody yet", and there doesn't need to be.
     */
    public function storeTeam(Request $request, SendShopInvitation $sendInvitation): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        abort_if(! $shop || $shop->isOnboarded(), 404);

        // A row left blank (e.g. "+ Add another" clicked then abandoned) is
        // dropped here rather than rejected - only rows with something
        // actually typed need to pass real validation.
        $request->merge([
            'invitations' => collect($request->input('invitations', []))
                ->filter(fn ($row) => filled($row['email'] ?? null))
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'invitations' => ['present', 'array'],
            'invitations.*.email' => ['required', 'email', 'max:255'],
            'invitations.*.role' => ['required', Rule::in(ShopPermissions::EDITABLE_ROLES)],
        ]);

        foreach ($validated['invitations'] as $row) {
            $sendInvitation($shop, $row['email'], $row['role'], Auth::user());
        }

        $shop->update(['onboarding_step' => 'catalog']);

        return redirect()->route('onboarding.show');
    }

    /**
     * Step 3 (skippable): a handful of quick-add catalog rows - name (plus
     * unit for materials) only, not the full CRUD forms those resources get
     * under Setup. Submitting this step also finishes onboarding; there's
     * no separate "done" screen to click through.
     */
    public function storeCatalog(Request $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        abort_if(! $shop || $shop->isOnboarded(), 404);

        // Same reasoning as storeTeam(): a row left blank is dropped, not
        // rejected.
        foreach (['materials', 'services', 'business_partners'] as $field) {
            $request->merge([
                $field => collect($request->input($field, []))
                    ->filter(fn ($row) => filled($row['name'] ?? null))
                    ->values()
                    ->all(),
            ]);
        }

        $validated = $request->validate([
            'materials' => ['present', 'array'],
            'materials.*.name' => ['required', 'string', 'max:255'],
            'materials.*.unit' => ['nullable', 'string', 'max:50'],
            'services' => ['present', 'array'],
            'services.*.name' => ['required', 'string', 'max:255'],
            'business_partners' => ['present', 'array'],
            'business_partners.*.name' => ['required', 'string', 'max:255'],
        ]);

        // shop_id fills itself in from the current user's shop (BelongsToShop),
        // same as every other controller that creates one of these.
        foreach ($validated['materials'] as $row) {
            Material::create(['name' => $row['name'], 'unit' => $row['unit'] ?? null]);
        }

        foreach ($validated['services'] as $row) {
            Service::create(['name' => $row['name']]);
        }

        foreach ($validated['business_partners'] as $row) {
            BusinessPartner::create(['name' => $row['name']]);
        }

        $shop->update(['onboarded_at' => now(), 'onboarding_step' => null]);

        return redirect()->route('dashboard')
            ->with('status', "Welcome to Fab Shop! \"{$shop->name}\" is ready to go.");
    }
}

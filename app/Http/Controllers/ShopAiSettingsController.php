<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Requests\UpdateShopAiSettingsRequest;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Support\AgentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShopAiSettingsController extends Controller
{
    public function edit(): Response
    {
        $shop = Auth::user()->currentShop;
        $setting = $shop->aiSetting;

        return Inertia::render('ShopSettings/Ai', [
            // The key itself is never sent - only a hint to recognise it.
            'setting' => $setting ? [
                'provider' => $setting->provider,
                'model' => $setting->model,
                'has_key' => $setting->hasKey(),
                'key_hint' => $setting->keyHint(),
                'last_tested_at' => $setting->last_tested_at?->toIso8601String(),
            ] : null,
            // Whether the plan (or trial) currently allows AI - when not,
            // the page explains instead of offering the form, and any saved
            // key waits for the shop to come back.
            'available' => $shop->hasFeature(Feature::AiAssist),
            'agentAvailable' => $shop->hasFeature(Feature::AiAgent),
            'mcp' => $this->mcp($shop),
            'providers' => collect(config('ai.providers'))->map(fn (array $provider) => [
                'label' => $provider['label'],
                'default_model' => $provider['default_model'],
                'models' => $provider['models'],
            ]),
        ]);
    }

    /**
     * The MCP connection panel's data. The token secret is only ever the
     * one-time flash from just creating it - the list shows names and dates.
     *
     * @return array<string, mixed>
     */
    private function mcp(Shop $shop): array
    {
        $agent = $shop->agents()->first();

        return [
            'url' => url('/mcp'),
            'grantable' => AgentAccess::grantable(),
            'enabled' => $agent !== null,
            'permissions' => $agent ? AgentAccess::permissionsFrom($agent->shops()->whereKey($shop->id)->first()?->pivot->permission_overrides ?? []) : [],
            'tokens' => $agent ? $agent->tokens()->latest()->get()->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ])->all() : [],
            'new_token' => session('new_token'),
            'default_token_days' => config('ai.mcp_token_days'),
        ];
    }

    public function update(UpdateShopAiSettingsRequest $request, AiManager $ai): RedirectResponse
    {
        $shop = $request->user()->currentShop;
        $data = $request->validated();

        // Build the candidate first and test it, so a wrong key is never
        // stored and the owner finds out now, not on the first quote.
        $setting = $shop->aiSetting ?? new ShopAiSetting(['source' => 'byok']);
        $setting->fill([
            'provider' => $data['provider'],
            'model' => $data['model'],
        ]);

        // Switching provider invalidates the old key (it belongs to a
        // different service), so a new one is required then.
        if ($setting->exists && $setting->isDirty('provider') && blank($data['api_key'] ?? null)) {
            return back()->withErrors(['api_key' => 'Enter an API key for the new provider.']);
        }

        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        try {
            $ai->test($setting);
        } catch (AiException $e) {
            return back()->withErrors(['api_key' => $e->getMessage()]);
        }

        $setting->shop_id = $shop->id;
        $setting->forceFill(['last_tested_at' => now()])->save();

        return back()->with('status', 'AI settings saved and connection verified.');
    }

    public function destroy(): RedirectResponse
    {
        Auth::user()->currentShop->aiSetting?->delete();

        return back()->with('status', 'AI provider and key removed.');
    }
}

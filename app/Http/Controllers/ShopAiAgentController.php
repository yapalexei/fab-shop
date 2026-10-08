<?php

namespace App\Http\Controllers;

use App\Actions\CreateShopAgent;
use App\Http\Requests\StoreShopAiAgentTokenRequest;
use App\Http\Requests\UpdateShopAiAgentRequest;
use App\Mcp\Tools\McpTool;
use App\Support\AgentAccess;
use App\Support\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Owner-only management of the shop's MCP connection: turn it on (creates the
 * AI agent identity), choose what it can read, mint and revoke tokens, turn
 * it off. The page itself is rendered by ShopAiSettingsController.
 */
class ShopAiAgentController extends Controller
{
    public function store(CreateShopAgent $createAgent): RedirectResponse
    {
        $createAgent(Auth::user()->currentShop);
        SecurityLog::record('AI connection turned on', Auth::user());

        return back()->with('status', 'AI connection turned on. Create a token to connect your AI.');
    }

    public function update(UpdateShopAiAgentRequest $request): RedirectResponse
    {
        $shop = $request->user()->currentShop;
        $agent = $shop->agents()->firstOrFail();

        $shop->agents()->updateExistingPivot($agent->id, [
            'permission_overrides' => AgentAccess::overridesFor($request->validated('permissions')),
        ]);

        SecurityLog::record('AI connection access changed', $request->user(), [
            'permissions' => $request->validated('permissions'),
        ]);

        return back()->with('status', 'What your AI can do has been updated.');
    }

    /**
     * Turning it off removes the agent and every token with it - they can't
     * be revived, only replaced by turning the connection on again.
     */
    public function destroy(): RedirectResponse
    {
        $shop = Auth::user()->currentShop;

        DB::transaction(function () use ($shop) {
            foreach ($shop->agents()->get() as $agent) {
                $agent->tokens()->delete();
                $shop->agents()->detach($agent->id);
                $agent->delete();
            }
        });

        SecurityLog::record('AI connection turned off', Auth::user());

        return back()->with('status', 'AI connection turned off and all its tokens removed.');
    }

    public function storeToken(StoreShopAiAgentTokenRequest $request): RedirectResponse
    {
        $agent = $request->user()->currentShop->agents()->firstOrFail();
        $days = $request->validated('expires_in_days') ?: config('ai.mcp_token_days');

        // The one place the secret exists in the clear: flashed once to the
        // very next page load, never stored (Sanctum keeps only a hash).
        // Read-only by default: a token only carries the right to change
        // things when the owner ticks the box. Which clients get that is the
        // main lever against the "lethal trifecta" - an AI that can read
        // private data, read untrusted text and act on both.
        $abilities = $request->boolean('allow_changes') ? ['mcp', McpTool::WRITE_ABILITY] : ['mcp'];

        $token = $agent->createToken($request->validated('name'), $abilities, now()->addDays($days));

        SecurityLog::record('AI token created', $request->user(), [
            'token_name' => $request->validated('name'),
            'expires_in_days' => $days,
        ]);

        return back()->with('new_token', $token->plainTextToken);
    }

    public function destroyToken(int $token): RedirectResponse
    {
        $agent = Auth::user()->currentShop->agents()->firstOrFail();

        // Looked up through this shop's own agent, so another shop's token
        // id simply isn't found.
        $agent->tokens()->whereKey($token)->firstOrFail()->delete();

        SecurityLog::record('AI token revoked', Auth::user(), ['token_id' => $token]);

        return back()->with('status', 'Token revoked. Anything using it is now disconnected.');
    }
}

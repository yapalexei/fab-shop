<?php

namespace App\Actions;

use App\Models\Shop;
use App\Models\User;
use App\Support\AgentAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the identity a shop's own AI connects as over MCP: a user row
 * that belongs to this one shop and nothing else, with read-only access from
 * AgentAccess::defaults(). It is not a person - no usable password, an email
 * on a reserved domain that can't receive mail, no role, no seat - and the
 * is_agent flag (not fillable) keeps it out of the team list and off the
 * login form. One agent per shop; calling this again returns it.
 */
class CreateShopAgent
{
    public function __invoke(Shop $shop): User
    {
        return DB::transaction(function () use ($shop) {
            $existing = $shop->agents()->first();

            if ($existing) {
                return $existing;
            }

            $agent = new User(['name' => 'AI agent']);
            $agent->forceFill([
                'email' => 'agent-'.$shop->id.'-'.Str::lower(Str::random(10)).'@agents.invalid',
                'password' => Str::random(64),
                'email_verified_at' => now(),
                'is_agent' => true,
                'current_shop_id' => $shop->id,
            ])->save();

            $shop->agents()->attach($agent->id, [
                'permission_overrides' => AgentAccess::overridesFor(AgentAccess::defaults()),
            ]);

            return $agent;
        });
    }
}

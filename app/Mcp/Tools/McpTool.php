<?php

namespace App\Mcp\Tools;

use App\Models\McpRequest;
use App\Models\Shop;
use App\Models\User;
use App\Support\UntrustedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Shared shape of every tool a shop's AI can call. Each tool names the one
 * permission it needs, and this base enforces it (as the agent user, the
 * same way the web app does - so changing the agent's access in Settings
 * changes what it can do immediately), hides tools the agent can't use,
 * turns any failure into an error the AI can read instead of a 500, and
 * writes a log row. Subclasses only fetch and shape data.
 *
 * Everything returned is data from the shop's own records, some of it typed
 * by customers or staff: a client must treat it as data, never instructions.
 *
 * Defences that live here, so every tool - including ones added later - gets
 * them without remembering to (see "Prompt injection" in CLAUDE.md):
 *  - a write needs a token that was created with permission to change things
 *    (ability mcp:write), so a client that also reads the web or an inbox can
 *    be given a read-only token and never hold all three legs of the "lethal
 *    trifecta" (private data + untrusted content + a way to send it out);
 *  - writes are capped per hour, to bound what a hijacked session can do;
 *  - text an AI writes may not contain links, markup or hidden characters
 *    (the usual way to smuggle data into something a person later sends);
 *  - everything returned has hidden characters stripped and is length-capped.
 * (PHP attributes aren't inherited, so each concrete tool carries its own
 * #[Name], #[IsReadOnly] and #[IsIdempotent].)
 */
abstract class McpTool extends Tool
{
    /** The token ability that lets a connection change things, not just read. */
    public const WRITE_ABILITY = 'mcp:write';

    /**
     * The permission the agent must hold, e.g. "quotes.view". Null for a tool
     * that is always available (the access report) or that checks per call
     * (catalog search, whose permission depends on what is being searched).
     */
    abstract protected function permission(): ?string;

    /**
     * Whether calling this changes anything - the create/update permissions.
     * Used to tell the AI which of its tools write.
     */
    public function writes(): bool
    {
        return (bool) preg_match('/\.(create|update)$/', (string) $this->permission());
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function run(Request $request, User $agent, Shop $shop): array;

    public function shouldRegister(Request $request): bool
    {
        $permission = $this->permission();
        $agent = $request->user();

        if ($permission !== null && ! ($agent?->hasShopPermission($permission) ?? false)) {
            return false;
        }

        // A read-only token is never even offered the tools that change things.
        return ! $this->writes() || ($agent?->tokenCan(self::WRITE_ABILITY) ?? false);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $agent = $request->user();
        $shop = $agent?->currentShop;
        $started = hrtime(true);

        $permission = $this->permission();

        if (! $agent || ! $shop || ($permission !== null && ! $agent->hasShopPermission($permission))) {
            $this->log($shop, $agent, $started, 'denied');

            return Response::error('Permission denied: this connection has not been given access to that part of the shop.');
        }

        if ($this->writes()) {
            if (! $agent->tokenCan(self::WRITE_ABILITY)) {
                $this->log($shop, $agent, $started, 'denied');

                return Response::error('This connection\'s token is read-only. The shop owner can create a token that is allowed to make changes in Settings > AI assistant.');
            }

            $key = 'mcp-writes:'.$agent->id;

            if (RateLimiter::tooManyAttempts($key, (int) config('ai.mcp_writes_per_hour'))) {
                $this->log($shop, $agent, $started, 'throttled');

                return Response::error('Too many changes in the last hour. This limit protects your shop data - ask the owner, or try again later.');
            }

            RateLimiter::hit($key, 3600);

            // Anything that looks like an attempt to smuggle a link, markup or
            // hidden text into a record is refused outright (and logged).
            if ($found = UntrustedText::firstProblem($request->all())) {
                $this->log($shop, $agent, $started, 'blocked');

                return Response::error("The field \"{$found['path']}\" contains {$found['reason']}, which the AI connection isn't allowed to write. Describe it in plain words instead, or ask the shop owner to add it in the app.");
            }
        }

        try {
            $data = $this->run($request, $agent, $shop);
        } catch (ValidationException $e) {
            $this->log($shop, $agent, $started, 'invalid');

            return Response::error(collect($e->errors())->flatten()->implode(' '));
        } catch (Throwable $e) {
            report($e);
            $this->log($shop, $agent, $started, 'error');

            return Response::error('Something went wrong reading that. Try again.');
        }

        $this->log($shop, $agent, $started, 'ok');

        // What goes back to the AI is stripped of hidden characters and capped
        // in length, so a record can't carry an invisible or enormous payload.
        return Response::structured(UntrustedText::cleanDeep($data));
    }

    /**
     * Record a change the connected AI made in the activity log, with the
     * agent as the actor, so it can always be told apart from a person's.
     */
    protected function audit(Model $subject, User $agent, string $what): void
    {
        activity('mcp')->performedOn($subject)->causedBy($agent)->log($what.' (via AI connection)');
    }

    /**
     * Only the tool name, status and timing - never arguments or results.
     */
    private function log(?Shop $shop, ?User $agent, int $started, string $status): void
    {
        if (! $shop) {
            return;
        }

        try {
            McpRequest::create([
                'shop_id' => $shop->id,
                'agent_id' => $agent?->id,
                'tool' => $this->name(),
                'status' => $status,
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

<?php

namespace App\Services\Ai;

use App\Models\AiRequest;
use App\Models\Shop;
use Throwable;

/**
 * Writes the usage row for an AI call (tokens, timing, outcome - never the
 * prompt or the answer, which hold customer data). shop_id is passed
 * explicitly because AI work can run without an authenticated user. A
 * failure to log is reported but never fails the feature that was used.
 */
final class AiUsage
{
    public static function record(Shop $shop, ?int $userId, string $feature, string $provider, string $model, ?AiResponse $response, int $startedAtNs, string $status): void
    {
        try {
            AiRequest::create([
                'shop_id' => $shop->id,
                'user_id' => $userId,
                'feature' => $feature,
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $response->inputTokens ?? 0,
                'output_tokens' => $response->outputTokens ?? 0,
                'latency_ms' => (int) ((hrtime(true) - $startedAtNs) / 1_000_000),
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

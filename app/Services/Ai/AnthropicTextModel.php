<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\TextModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic's Messages API, called through Laravel's HTTP client (like
 * QuickBooksClient) so every test can use Http::fake(). The model is the
 * shop's own choice and the key is the shop's own - this class holds neither
 * beyond the lifetime of one call.
 *
 * No temperature/thinking parameters on purpose: current models reject
 * non-default sampling, and the defaults are right for extraction work.
 */
class AnthropicTextModel implements TextModel
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $system, string $prompt, ?array $schema = null): AiResponse
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => config('ai.max_output_tokens'),
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];

        if ($schema !== null) {
            $body['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        }

        try {
            $response = Http::baseUrl(config('ai.providers.anthropic.base_url'))
                ->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])
                ->timeout(config('ai.timeout'))
                ->acceptJson()
                ->post('/v1/messages', $body);
        } catch (ConnectionException) {
            throw AiException::unreachable();
        }

        if ($response->failed()) {
            throw AiException::fromStatus($response->status());
        }

        if ($response->json('stop_reason') === 'refusal') {
            throw AiException::refused();
        }

        // Content is a list of typed blocks; only text blocks are the answer.
        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $data = null;
        if ($schema !== null) {
            $data = json_decode($text, true);

            if (! is_array($data)) {
                throw AiException::malformed();
            }
        }

        return new AiResponse(
            text: $text,
            data: $data,
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            model: (string) $response->json('model', $this->model),
        );
    }
}

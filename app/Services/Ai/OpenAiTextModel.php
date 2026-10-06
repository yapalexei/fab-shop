<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\TextModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's Chat Completions API through Laravel's HTTP client - same shape
 * and same reasons as AnthropicTextModel.
 */
class OpenAiTextModel implements TextModel
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function complete(string $system, string $prompt, ?array $schema = null): AiResponse
    {
        $body = [
            'model' => $this->model,
            'max_completion_tokens' => config('ai.max_output_tokens'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($schema !== null) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'result', 'strict' => true, 'schema' => $schema],
            ];
        }

        try {
            $response = Http::baseUrl(config('ai.providers.openai.base_url'))
                ->withToken($this->apiKey)
                ->timeout(config('ai.timeout'))
                ->acceptJson()
                ->post('/v1/chat/completions', $body);
        } catch (ConnectionException) {
            throw AiException::unreachable();
        }

        if ($response->failed()) {
            throw AiException::fromStatus($response->status());
        }

        if ($response->json('choices.0.message.refusal') !== null) {
            throw AiException::refused();
        }

        $text = (string) $response->json('choices.0.message.content', '');

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
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            model: (string) $response->json('model', $this->model),
        );
    }
}

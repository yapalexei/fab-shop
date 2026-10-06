<?php

namespace App\Services\Ai;

/**
 * One completed model call. $data is the decoded JSON when a schema was
 * requested, otherwise null; $text is always the raw text.
 */
final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public string $text,
        public ?array $data,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {}
}

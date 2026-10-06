<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiException;
use App\Services\Ai\AiResponse;

/**
 * The main LLM (System 2): reads and writes text. Implementations are one per
 * provider; callers get one from AiManager and never care which.
 */
interface TextModel
{
    /**
     * @param  array<string, mixed>|null  $schema  A JSON Schema the answer must follow; when given, AiResponse::$data holds the decoded result.
     *
     * @throws AiException
     */
    public function complete(string $system, string $prompt, ?array $schema = null): AiResponse;
}

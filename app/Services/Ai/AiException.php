<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Anything that stops an AI call: not configured, not entitled, or the
 * provider said no. The message is always safe to show the shop owner - it
 * never contains the API key or the provider's raw response.
 */
class AiException extends RuntimeException
{
    public static function notEntitled(): self
    {
        return new self('AI assistant is not included in this shop\'s plan.');
    }

    public static function notConfigured(): self
    {
        return new self('AI is not set up yet. Add a provider and API key in Settings > AI.');
    }

    public static function fromStatus(int $status): self
    {
        return new self(match (true) {
            in_array($status, [401, 403], true) => 'The provider rejected the API key. Check that it is correct and still active.',
            $status === 404 => 'The provider does not recognise this model. Pick a different one.',
            $status === 429 => 'The provider is rate limiting this key or the account is out of credit. Try again shortly.',
            $status >= 500 => 'The provider is having trouble right now. Try again shortly.',
            default => 'The provider could not process the request.',
        });
    }

    public static function unreachable(): self
    {
        return new self('Could not reach the provider. Check your connection and try again.');
    }

    public static function refused(): self
    {
        return new self('The model declined this request.');
    }

    public static function malformed(): self
    {
        return new self('The provider returned a response that could not be read.');
    }
}

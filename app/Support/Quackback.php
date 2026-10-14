<?php

namespace App\Support;

use App\Models\User;
use RuntimeException;

/**
 * The Quackback feedback widget. `services.quackback.url` is the only thing
 * that turns it on (an anonymous launcher for every visitor); the signing
 * secret is separate and server-only. It signs the short-lived ssoToken that
 * links a signed-in user to their feedback, so the browser never claims an
 * identity of its own. See docs/context/feedback-widget.md.
 */
class Quackback
{
    /** How long a token is accepted for - Quackback asks for roughly five minutes. */
    public const TOKEN_LIFETIME_SECONDS = 300;

    /**
     * The instance origin ("https://host[:port]", no path or trailing slash),
     * or null when the widget isn't configured or the value isn't a usable
     * http(s) URL. Used to build the script URL and to extend the CSP, so it
     * is normalised rather than trusted as typed.
     */
    public static function url(): ?string
    {
        $url = trim((string) config('services.quackback.url'));
        $parts = $url === '' ? false : parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** Whether the server can sign tokens for signed-in users. */
    public static function canIdentify(): bool
    {
        return self::url() !== null && filled(config('services.quackback.signing_secret'));
    }

    /**
     * An HS256 JWT for $user: `sub` is the stable user id (never the email,
     * which can change), `email` and `name` keep the profile current.
     */
    public static function ssoToken(User $user): string
    {
        $secret = (string) config('services.quackback.signing_secret');

        if ($secret === '') {
            throw new RuntimeException('The Quackback signing secret is not configured.');
        }

        $header = self::base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = self::base64Url(json_encode([
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'exp' => now()->addSeconds(self::TOKEN_LIFETIME_SECONDS)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return "{$header}.{$claims}.".self::base64Url(hash_hmac('sha256', "{$header}.{$claims}", $secret, true));
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

<?php

namespace App\Support;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) two-factor sign-in: the setup secret, code checks and
 * single-use recovery codes. Recovery codes are shown to the user once and
 * kept only as SHA-256 hashes - they are random and long, so a fast hash is
 * enough and a leaked table can't be turned back into working codes.
 */
class TwoFactor
{
    public const RECOVERY_CODE_COUNT = 8;

    public static function google2fa(): Google2FA
    {
        return new Google2FA;
    }

    public static function newSecret(): string
    {
        return self::google2fa()->generateSecretKey(32);
    }

    public static function otpAuthUri(User $user, string $secret): string
    {
        return self::google2fa()->getQRCodeUrl(config('app.name'), $user->email, $secret);
    }

    /**
     * Whether `$code` is currently valid for the user's secret. Accepts one
     * 30-second window either side for clock drift, and refuses a window that
     * was already used, so a code that was shoulder-surfed or intercepted
     * can't be replayed.
     */
    public static function verify(User $user, string $code): bool
    {
        $step = self::google2fa()->verifyKeyNewer(
            $user->two_factor_secret,
            preg_replace('/\s+/', '', $code),
            // 0, not null: with no previous step the library answers a bare
            // `true` instead of the matched step, and nothing would be
            // recorded to block a replay of this first code.
            $user->two_factor_last_step ?? 0,
            1,
        );

        if (! is_int($step)) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /**
     * Replace the user's recovery codes; returns the plain codes to show once.
     *
     * @return list<string>
     */
    public static function regenerateRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => strtolower(bin2hex(random_bytes(3)).'-'.bin2hex(random_bytes(3))))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(self::hashCode(...), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Use up a recovery code; false if it isn't one of the user's.
     */
    public static function consumeRecoveryCode(User $user, string $code): bool
    {
        $hash = self::hashCode($code);
        $codes = $user->two_factor_recovery_codes ?? [];

        $index = collect($codes)->search(fn (string $stored) => hash_equals($stored, $hash));

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    private static function hashCode(string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }
}

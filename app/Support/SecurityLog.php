<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * The audit trail for security-relevant events (sign-ins, failures, password
 * and two-factor changes, access changes) - the "who did what, when, from
 * where" an auditor or an incident review asks for.
 *
 * Stored in the activity log under the `security` log name. It is
 * deliberately not tied to a shop (a failed login has no shop, and a user can
 * belong to several), so the shop, when known, goes in the properties. Never
 * pass a password, token, code or secret as a property.
 */
class SecurityLog
{
    public const LOG = 'security';

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, ?User $user = null, array $properties = []): void
    {
        $logger = activity(self::LOG)->withProperties([
            ...$properties,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent() ? mb_substr(Request::userAgent(), 0, 255) : null,
            'shop_id' => $user?->current_shop_id,
        ]);

        if ($user) {
            $logger->causedBy($user);
        }

        $logger->log($event);
    }
}

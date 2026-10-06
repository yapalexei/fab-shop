<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ShopInvitationSent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates (or refreshes) an invitation for $email to join $shop as $role,
 * and emails it. Shared by onboarding's team step and the Team page's own
 * "invite by email" action, so there's exactly one place that decides what
 * a fresh invite looks like.
 */
class SendShopInvitation
{
    public function __invoke(Shop $shop, string $email, string $role, ?User $invitedBy = null): Invitation
    {
        $email = Str::lower(trim($email));

        // Seats are only checked here for someone who'd take a new one: a
        // refreshed invite already holds its seat (hasRoomForSeats ignores it).
        if (! $shop->hasRoomForSeats(1, $email)) {
            throw ValidationException::withMessages(['email' => $shop->seatLimitMessage()]);
        }

        // Re-inviting the same address refreshes the one row (new token,
        // new expiry, any prior acceptance cleared) rather than piling up
        // duplicates - the unique(shop_id, email) index backs this up.
        $invitation = Invitation::updateOrCreate(
            ['shop_id' => $shop->id, 'email' => $email],
            [
                'role' => $role,
                'token' => Str::random(40),
                'invited_by' => $invitedBy?->id,
                'expires_at' => now()->addDays(config('shop.invitation_expiry_days')),
                'accepted_at' => null,
            ],
        );

        // On-demand route, not ->notify() on a User - the invitee usually
        // doesn't have an account yet.
        Notification::route('mail', $email)->notify(new ShopInvitationSent($invitation));

        return $invitation;
    }
}

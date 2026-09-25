<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent via an on-demand route (Notification::route('mail', $email)->notify(...))
 * rather than to a User, since the invitee may not have an account yet.
 */
class ShopInvitationSent extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviterName = $this->invitation->invitedBy?->name ?? 'Someone';
        $shopName = $this->invitation->shop->name;

        return (new MailMessage)
            ->subject("You're invited to join {$shopName}")
            ->line("{$inviterName} has invited you to join \"{$shopName}\" on Fab Shop as {$this->invitation->role}.")
            ->action('Accept invitation', route('invitations.show', $this->invitation->token))
            ->line("This invitation expires on {$this->invitation->expires_at->format('M j, Y')}.");
    }
}

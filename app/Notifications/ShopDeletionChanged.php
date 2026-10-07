<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a shop's people that its deletion was scheduled, or called off.
 */
class ShopDeletionChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $shopName, public ?\DateTimeInterface $scheduledFor = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->scheduledFor === null) {
            return (new MailMessage)
                ->subject("{$this->shopName} will not be deleted")
                ->line("The owner cancelled the deletion of \"{$this->shopName}\". Nothing was removed.");
        }

        return (new MailMessage)
            ->subject("{$this->shopName} is scheduled for deletion")
            ->line("The owner of \"{$this->shopName}\" asked for the shop and all its data to be deleted.")
            ->line('It will be permanently erased on '.$this->scheduledFor->format('M j, Y').'. Until then the shop keeps working and the owner can cancel.')
            ->line('Your own account is not deleted. If you belong to no other shop you can start or join one.');
    }
}

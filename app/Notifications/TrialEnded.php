<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEnded extends Notification
{
    use Queueable;

    public function __construct(public Shop $shop)
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
        return (new MailMessage)
            ->subject('Your free trial has ended')
            ->line("The 14-day trial for \"{$this->shop->name}\" has ended.")
            ->line('Upgrade to a paid plan to keep using Customers, the Quote builder, and the job board.')
            ->action('Choose a plan', url('/'));
    }
}

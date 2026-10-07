<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CarbonInterface $deadline)
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
            ->subject('Please set up two-factor sign-in')
            ->line('Your account now needs two-factor sign-in: a code from an authenticator app in addition to your password.')
            ->line("Please set it up by {$this->deadline->format('M j, Y')}. After that date you will be asked to finish setup before you can keep working.")
            ->action('Set up two-factor sign-in', route('profile.edit'))
            ->line('It takes about a minute. Recovery codes are provided in case you lose your phone.');
    }
}

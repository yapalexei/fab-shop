<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Shop $shop) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) config('shop.export_retention_days');

        return (new MailMessage)
            ->subject("Your data export for {$this->shop->name} is ready")
            ->line('The export you asked for is ready to download from Shop settings.')
            ->action('Open Shop settings', route('shop.settings.edit'))
            ->line("The file is kept for {$days} days, then removed.");
    }
}

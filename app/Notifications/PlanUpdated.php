<?php

namespace App\Notifications;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\Shop;
use App\Support\Billing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells a shop's owner what is different on their plan, as a before/after
 * rather than a bare "your plan changed": what they now get on top of what
 * they had, the price (lowered and already in effect; raised from a date
 * ahead, with the right to cancel first; or raised with effect now), a change
 * in seats, and, rarely, a feature that is gone from every plan because we
 * can no longer support it.
 *
 * Built from the same `$changes` array Shop::announcePlanChange() stores for
 * the Plan page. Only changes that matter to the shop are sent: a higher list
 * price that doesn't reach them is never announced. What it promises about
 * notice matches section 4 of the Terms of Service.
 */
class PlanUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{plan_name: string, added: list<string>, retired: list<string>, price: array{from_cents: int, to_cents: int, effective_at: string}|null, seats: array{from: int|null, to: int|null}|null}  $changes
     */
    public function __construct(public Shop $shop, public array $changes)
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
        $plan = $this->changes['plan_name'];
        $price = $this->changes['price'] ?? null;
        $added = $this->features($this->changes['added'] ?? []);
        $retired = $this->features($this->changes['retired'] ?? []);

        $mail = (new MailMessage)
            ->subject(match (true) {
                $price !== null && $price['to_cents'] < $price['from_cents'] => "Good news: your {$plan} plan is cheaper",
                $price !== null && $this->isFuture($price) => "Your {$plan} plan price is changing on {$this->date($price)}",
                $price !== null => "Your {$plan} plan price has changed",
                $added !== [] => "More is included in your {$plan} plan",
                default => 'A feature is no longer available',
            })
            ->line("Here is what is different on your {$plan} plan for \"{$this->shop->name}\".");

        if ($price !== null) {
            $interval = $this->shop->billingInterval();
            $per = $interval === BillingInterval::Annual ? 'year' : 'month';
            $from = $this->money(Billing::periodPriceCents($price['from_cents'], $interval));
            $to = $this->money(Billing::periodPriceCents($price['to_cents'], $interval));
            $date = $this->date($price);

            if ($price['to_cents'] < $price['from_cents']) {
                $mail->line("**Price:** it has gone down from {$from} to {$to} per {$per}, and the new price is already in effect. "
                    ."You don't need to do anything. Amounts you have already paid aren't adjusted - your next payment is at the lower price.");
            } elseif ($this->isFuture($price)) {
                $mail->line("**Price:** it will go up from {$from} to {$to} per {$per} on {$date}. "
                    ."Nothing changes before then, and what you have already paid for isn't affected - the new price applies to your first payment on or after {$date}.");
                $mail->line("You don't need to do anything to continue. If you would rather not continue at the new price, you can cancel before {$date} and you won't be charged it - you keep access until the end of the period you've paid for. "
                    .'This is covered in the "Price changes" part of our [Terms of Service]('.route('legal.terms').').');
            } else {
                $mail->line("**Price:** it has gone up from {$from} to {$to} per {$per}, effective now. The new price applies to your next payment; "
                    ."what you've already paid for isn't affected. See \"Price changes\" in our [Terms of Service](".route('legal.terms').').');
            }
        }

        if (($this->changes['seats'] ?? null) !== null) {
            $seats = $this->changes['seats'];
            $mail->line('**Seats included:** '.($seats['from'] ?? 'unlimited').' → '.($seats['to'] ?? 'unlimited').$this->seatsWhen());
        }

        if ($added !== []) {
            $mail->line('**Now included in your plan, on top of what you had:**');

            foreach ($added as $feature) {
                $mail->line("- {$feature->label()}: {$feature->tagline()}");
            }
        }

        if ($retired !== []) {
            $mail->line('**No longer available:**');

            foreach ($retired as $feature) {
                $mail->line("- {$feature->label()}");
            }

            $mail->line('We can no longer support '.(count($retired) === 1 ? 'this feature' : 'these features').', so '
                .(count($retired) === 1 ? 'it has' : 'they have').' been removed from every plan. '
                .'If you were paying for '.(count($retired) === 1 ? 'it' : 'them').' as an add-on, that has been cancelled and you will not be billed for it again.');
        }

        return $mail
            ->line($retired === [] ? 'You keep every other feature you have today. There is nothing you need to do.' : 'You keep every other feature you have today.')
            ->action('See your plan', route('shop.plan.index'));
    }

    /** Whether the price change is still ahead (a scheduled increase). */
    private function isFuture(array $price): bool
    {
        return Carbon::parse($price['effective_at'])->isFuture();
    }

    private function date(array $price): string
    {
        return Carbon::parse($price['effective_at'])->setTimezone($this->shop->localTimezone())->isoFormat('MMMM D, YYYY');
    }

    private function seatsWhen(): string
    {
        $price = $this->changes['price'] ?? null;

        return $price !== null && $this->isFuture($price) ? ", from {$this->date($price)}." : '.';
    }

    /**
     * @param  list<string>  $values
     * @return list<Feature>
     */
    private function features(array $values): array
    {
        return array_values(array_filter(array_map(fn (string $value) => Feature::tryFrom($value), $values)));
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }
}

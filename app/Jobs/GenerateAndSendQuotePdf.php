<?php

namespace App\Jobs;

use App\Actions\GenerateQuotePdf;
use App\Mail\QuoteReadyMail;
use App\Models\Quote;
use App\Support\QuoteHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class GenerateAndSendQuotePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function handle(GenerateQuotePdf $generatePdf): void
    {
        $generatePdf($this->quote);

        Mail::to($this->quote->customer->email)->send(new QuoteReadyMail($this->quote));

        // Recorded only once the mail was handed off, so the quote page can
        // tell "queued" from "sent" - and a failed job leaves it null.
        $this->quote->update(['emailed_at' => now(), 'email_failed_at' => null]);
    }

    /**
     * The send gave up after its retries. Recorded so the quote page can say
     * so, the customer can be corrected, and the log shows what happened.
     */
    public function failed(Throwable $exception): void
    {
        $this->quote->update(['email_failed_at' => now()]);

        QuoteHistory::log(
            $this->quote,
            'email_failed',
            "Email to {$this->quote->customer->email} failed to deliver - ".Str::limit($exception->getMessage(), 200),
        );
    }
}

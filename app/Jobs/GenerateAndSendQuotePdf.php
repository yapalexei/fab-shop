<?php

namespace App\Jobs;

use App\Actions\GenerateQuotePdf;
use App\Mail\QuoteReadyMail;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateAndSendQuotePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function handle(GenerateQuotePdf $generatePdf): void
    {
        $generatePdf($this->quote);

        Mail::to($this->quote->customer->email)->send(new QuoteReadyMail($this->quote));
    }
}

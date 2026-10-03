<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Quote from {$this->quote->shop->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.quote-ready',
            with: ['quote' => $this->quote],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->quote->pdf()->disk, $this->quote->pdf()->getPathRelativeToRoot())
                ->as("quote-{$this->quote->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}

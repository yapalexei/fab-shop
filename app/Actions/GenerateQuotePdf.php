<?php

namespace App\Actions;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a quote to PDF and stores it as the quote's single 'pdf' media
 * file, replacing any previous version - re-running this after edits keeps
 * the stored PDF in sync with the quote's current line items.
 */
class GenerateQuotePdf
{
    public function __invoke(Quote $quote): void
    {
        $binary = Pdf::loadView('quotes.pdf', ['quote' => $quote])->output();

        $quote->addMediaFromString($binary)
            ->usingFileName("quote-{$quote->id}.pdf")
            ->toMediaCollection('pdf');
    }
}

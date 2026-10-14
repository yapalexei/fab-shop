<?php

namespace App\Enums;

enum Feature: string
{
    case Customers = 'customers';
    case QuoteBuilder = 'quote-builder';
    case JobBoard = 'job-board';
    case QuickbooksSync = 'quickbooks-sync';
    case MaterialTracking = 'material-tracking';
    case QualificationRecords = 'qualification-records';
    case MobileApi = 'mobile-api';
    case QuoteHistory = 'quote-history';
    case QuoteEmailing = 'quote-emailing';
    case AiAssist = 'ai-assist';
    case AiAgent = 'ai-agent';

    public function label(): string
    {
        return match ($this) {
            self::Customers => 'Customers',
            self::QuoteBuilder => 'Quote builder',
            self::JobBoard => 'Job board',
            self::QuickbooksSync => 'QuickBooks sync',
            self::MaterialTracking => 'Material & scrap tracking',
            self::QualificationRecords => 'Qualification records',
            self::MobileApi => 'Mobile app & API access',
            self::QuoteHistory => 'Quote history',
            self::QuoteEmailing => 'Emailing quotes to customers',
            self::AiAssist => 'AI assistant',
            self::AiAgent => 'AI agent access (MCP)',
        };
    }

    /**
     * Whether this feature actually exists in the app yet. MobileApi (F6) is
     * already wired into the Pro plan's feature list for when it ships, but
     * nothing enforces or exposes it today - the marketing page shows it as
     * "Coming soon" rather than a checkmark, so a tier's page never claims
     * more than the app can currently do. Flip to true here the day it ships.
     *
     * QuickbooksSync is built but deliberately switched off (a future
     * feature until the core app is settled): its routes, nav entry and the
     * invoice push all key off this method, so flipping it back to true
     * re-enables everything at once.
     */
    public function isShipped(): bool
    {
        if ($this->isDiscontinued()) {
            return false;
        }

        return match ($this) {
            self::Customers, self::QuoteBuilder, self::JobBoard,
            self::MaterialTracking, self::QualificationRecords, self::QuoteHistory, self::QuoteEmailing, self::AiAssist, self::AiAgent => true,
            self::QuickbooksSync, self::MobileApi => false,
        };
    }

    /**
     * A feature we can no longer support, gone from every plan and add-on.
     * Rare, and a decision made in code (like isShipped()): add its value to
     * `shop.discontinued_features` (config/shop.php) and deploy. Then no shop has the feature any more (Shop::hasFeature()
     * says no, whatever its plan row, carried-over features, add-ons or trial
     * say), it can't be bought or put in a tier, and `app:announce-discontinued-features`
     * cancels any add-on billing for it and tells each affected owner once.
     */
    public function isDiscontinued(): bool
    {
        return in_array($this->value, config('shop.discontinued_features', []), true);
    }

    /**
     * A one-line, customer-facing description - used on the marketing page's
     * feature highlights, not just the tier comparison checklist.
     */
    public function tagline(): string
    {
        return match ($this) {
            self::Customers => 'Every customer, quote and job in one place instead of scattered spreadsheets.',
            self::QuoteBuilder => 'Price a quote from your own material, labor and markup - not a blank spreadsheet.',
            self::JobBoard => 'A live traveler board from cutting to shipped, with full status history.',
            self::QuickbooksSync => 'Invoices push to QuickBooks automatically - no re-typing a won quote.',
            self::MaterialTracking => 'Track material and scrap against the job so estimates get sharper over time.',
            self::QualificationRecords => 'Track shop procedures, who is certified for them, and whether the job was actually covered.',
            self::MobileApi => 'Check and update job status from the shop floor or a connected app.',
            self::QuoteHistory => 'See what changed on every quote, when, and who changed it.',
            self::QuoteEmailing => 'Email the quote PDF straight to your customer from the quote page.',
            self::AiAssist => 'Draft quotes from a customer email and follow-ups on stale quotes, using your own AI account.',
            self::AiAgent => 'Let your own AI assistant read and update your shop data - customers, quotes, jobs and catalog.',
        };
    }

    /**
     * What the feature costs as a standalone add-on until an admin edits it
     * (FeatureSetting seeds each new row from this).
     */
    public function defaultPriceCents(): int
    {
        return match ($this) {
            self::QuoteHistory, self::QuoteEmailing => 1000,
            // Priced as the cross-tier escape hatch: well under the gap
            // between the Shop and Pro plans, so Pro stays the obvious pick
            // for a shop that wants both.
            self::MaterialTracking, self::QualificationRecords => 2500,
            default => 2000,
        };
    }
}

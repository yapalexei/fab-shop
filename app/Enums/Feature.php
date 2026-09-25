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
        };
    }

    /**
     * Whether this feature actually exists in the app yet. MobileApi (F6) is
     * already wired into the Pro plan's feature list for when it ships, but
     * nothing enforces or exposes it today - the marketing page shows it as
     * "Coming soon" rather than a checkmark, so a tier's page never claims
     * more than the app can currently do. Flip to true here the day it ships.
     */
    public function isShipped(): bool
    {
        return match ($this) {
            self::Customers, self::QuoteBuilder, self::JobBoard, self::QuickbooksSync,
            self::MaterialTracking, self::QualificationRecords => true,
            self::MobileApi => false,
        };
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
        };
    }
}

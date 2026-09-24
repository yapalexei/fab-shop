<?php

namespace App\Enums;

/**
 * Everything in a shop that access can be granted to, and the actions each
 * one supports. A permission's name is "{resource}.{action}" (e.g.
 * "quotes.delete"), registered as a Gate ability of the same name - see
 * App\Support\ShopPermissions for the full list and each role's defaults.
 */
enum ShopResource: string
{
    case Customers = 'customers';
    case Quotes = 'quotes';
    case Jobs = 'jobs';
    case Materials = 'materials';
    case Services = 'services';
    case BusinessPartners = 'business-partners';
    case Equipment = 'equipment';
    case Tooling = 'tooling';
    case Markup = 'markup';
    case QuickBooks = 'quickbooks';
    case Team = 'team';
    case Financials = 'financials';
    case ShopSettings = 'shop-settings';

    public function label(): string
    {
        return match ($this) {
            self::Customers => 'Customers',
            self::Quotes => 'Quotes',
            self::Jobs => 'Jobs',
            self::Materials => 'Materials',
            self::Services => 'Services',
            self::BusinessPartners => 'Business partners',
            self::Equipment => 'Equipment',
            self::Tooling => 'Tooling',
            self::Markup => 'Default markup',
            self::QuickBooks => 'QuickBooks',
            self::Team => 'Team',
            self::Financials => 'Financials',
            self::ShopSettings => 'Shop settings',
        };
    }

    /**
     * The actions this resource supports, each with a plain description of
     * what it actually lets someone do - several deliberately differ from a
     * generic create/update/delete (e.g. deleting a quote is only possible
     * before it became a job; cancelling a job can't be undone).
     *
     * @return array<string, string>
     */
    public function actions(): array
    {
        $catalog = fn (string $things) => [
            'view' => "See {$things}",
            'create' => "Add {$things}",
            'update' => "Edit {$things}",
            'delete' => "Delete {$things} (quote lines that used them keep their description and price)",
        ];

        return match ($this) {
            self::Customers => [
                'view' => 'See customers',
                'create' => 'Add customers',
                'update' => 'Edit customer details',
                'delete' => 'Delete customers - only ones with no quotes or jobs',
            ],
            self::Quotes => [
                'view' => 'See quotes and download PDFs',
                'create' => 'Start new quotes',
                'update' => 'Edit quotes, send them, mark won/lost, archive',
                'delete' => 'Delete quotes - only ones that never became a job',
            ],
            self::Jobs => [
                'view' => 'See the job board and job details',
                'create' => 'Convert won quotes into jobs',
                'update' => 'Move jobs between stations and add/remove photos',
                'cancel' => 'Cancel jobs - this cannot be undone',
            ],
            self::Materials => $catalog('materials'),
            self::Services => $catalog('services'),
            self::BusinessPartners => $catalog('business partners'),
            self::Equipment => $catalog('equipment'),
            self::Tooling => $catalog('tooling'),
            self::Markup => [
                'view' => 'See default markups',
                'update' => 'Change default markups for new quote lines',
            ],
            self::QuickBooks => [
                'view' => 'See the QuickBooks connection',
                'update' => 'Connect or reconnect QuickBooks',
                'delete' => 'Disconnect QuickBooks - invoices stop syncing',
            ],
            self::Team => [
                'view' => 'See the team list',
                'update' => 'Set hourly rates',
                'manage-access' => "Change Staff members' access (never beyond their own)",
            ],
            self::Financials => [
                'view' => 'Revenue, job and quote values, and win rate on the dashboard',
            ],
            self::ShopSettings => [
                'update' => 'Shop name, timezone, dashboard thresholds, job board drag-and-drop',
            ],
        };
    }

    public function permission(string $action): string
    {
        return "{$this->value}.{$action}";
    }
}

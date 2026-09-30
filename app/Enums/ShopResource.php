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
    case JobStages = 'job-stages';
    case Materials = 'materials';
    case Services = 'services';
    case BusinessPartners = 'business-partners';
    case Equipment = 'equipment';
    case Tooling = 'tooling';
    case MaterialTypes = 'material-types';
    case Procedures = 'procedures';
    case Certifications = 'certifications';
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
            self::JobStages => 'Job board stages',
            self::Materials => 'Materials',
            self::Services => 'Services',
            self::BusinessPartners => 'Business partners',
            self::Equipment => 'Equipment',
            self::Tooling => 'Tooling',
            self::MaterialTypes => 'Material types',
            self::Procedures => 'Procedures',
            self::Certifications => 'Certifications',
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
            self::JobStages => [
                'view' => 'See the job board\'s configured stages',
                'create' => 'Add custom stages to the job board',
                'update' => 'Rename and reorder custom stages',
                'delete' => 'Delete custom stages - only ones no job currently sits in',
            ],
            self::Materials => $catalog('materials'),
            self::Services => $catalog('services'),
            self::BusinessPartners => $catalog('business partners'),
            self::Equipment => $catalog('equipment'),
            self::Tooling => $catalog('tooling'),
            self::MaterialTypes => [
                'view' => 'See material types',
                'create' => 'Add custom material types (up to the shop limit)',
                'update' => 'Rename custom material types',
                'delete' => 'Delete custom material types - only ones no tool or material uses',
            ],
            self::Procedures => [
                'view' => 'See procedures',
                'create' => 'Add procedures',
                'update' => 'Edit procedures',
                'delete' => 'Delete procedures - only ones with no certifications or job records against them',
            ],
            self::Certifications => [
                'view' => 'See certifications',
                'create' => 'Add certifications',
                'update' => 'Edit certifications',
                'delete' => 'Delete certifications',
            ],
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

    /**
     * The plan feature that unlocks writing to this resource, or null for
     * resources every shop always has (team, financials, shop settings).
     * When a shop has lost that feature (e.g. its trial ran out) the
     * resource stays viewable but every non-view permission is withheld -
     * see User::resolveShopPermissions().
     */
    public function feature(): ?Feature
    {
        return match ($this) {
            self::Customers => Feature::Customers,
            self::Quotes, self::Materials, self::Services, self::BusinessPartners,
            self::Equipment, self::Tooling, self::MaterialTypes, self::Markup => Feature::QuoteBuilder,
            self::Jobs, self::JobStages => Feature::JobBoard,
            self::Procedures, self::Certifications => Feature::QualificationRecords,
            self::QuickBooks => Feature::QuickbooksSync,
            self::Team, self::Financials, self::ShopSettings => null,
        };
    }

    public function permission(string $action): string
    {
        return "{$this->value}.{$action}";
    }
}

<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\BusinessPartner;
use App\Models\Shop;

/**
 * Only the partner's own fields - not the materials/services it supplies
 * and at what price (a many-to-many with a price on the pivot doesn't fit
 * a flat CSV row). Set capabilities up afterward on the partner's own page.
 */
class BusinessPartnerImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'business-partners';
    }

    public function label(): string
    {
        return 'Business partners';
    }

    public function modelClass(): string
    {
        return BusinessPartner::class;
    }

    public function feature(): Feature
    {
        return Feature::QuoteBuilder;
    }

    public function templateColumns(): array
    {
        return ['name', 'email', 'phone', 'address', 'notes'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        return [
            'attributes' => [
                'name' => $row['name'],
                'email' => $row['email'] ?: null,
                'phone' => $row['phone'] ?: null,
                'address' => $row['address'] ?: null,
                'notes' => $row['notes'] ?: null,
            ],
            'relations' => [],
            'errors' => [],
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

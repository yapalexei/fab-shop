<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\Service;
use App\Models\Shop;

class ServiceImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'services';
    }

    public function label(): string
    {
        return 'Services';
    }

    public function modelClass(): string
    {
        return Service::class;
    }

    public function feature(): Feature
    {
        return Feature::QuoteBuilder;
    }

    public function templateColumns(): array
    {
        return ['name', 'notes'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        return [
            'attributes' => [
                'name' => $row['name'],
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
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

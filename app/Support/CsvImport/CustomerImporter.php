<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\Customer;
use App\Models\Shop;

class CustomerImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return 'Customers';
    }

    public function modelClass(): string
    {
        return Customer::class;
    }

    public function feature(): Feature
    {
        return Feature::Customers;
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

<?php

namespace App\Support\CsvImport;

use App\Enums\BillingMethod;
use App\Enums\Feature;
use App\Models\Equipment;
use App\Models\Shop;
use Illuminate\Validation\Rule;

class EquipmentImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'equipment';
    }

    public function label(): string
    {
        return 'Equipment';
    }

    public function modelClass(): string
    {
        return Equipment::class;
    }

    public function feature(): Feature
    {
        return Feature::QuoteBuilder;
    }

    public function templateColumns(): array
    {
        return ['name', 'billing_method', 'rate', 'notes'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        $billingMethod = BillingMethod::tryFrom(mb_strtolower($row['billing_method']));
        $errors = [];

        if ($row['billing_method'] !== '' && $billingMethod === null) {
            $errors[] = "Billing method \"{$row['billing_method']}\" must be \"hourly\" or \"flat\".";
        }

        return [
            'attributes' => [
                'name' => $row['name'],
                'billing_method' => $billingMethod?->value,
                'rate' => $row['rate'] !== '' ? $row['rate'] : null,
                'notes' => $row['notes'] ?: null,
            ],
            'relations' => [],
            'errors' => $errors,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'billing_method' => ['required', Rule::enum(BillingMethod::class)],
            'rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

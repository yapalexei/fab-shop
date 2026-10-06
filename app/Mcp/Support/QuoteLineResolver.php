<?php

namespace App\Mcp\Support;

use App\Enums\LineItemType;
use App\Models\Equipment;
use App\Models\MarkupDefault;
use App\Models\Material;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tool;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turns the line items an AI sends into rows a quote can store, the same
 * way the quote builder does when a person picks a catalog item: the cost
 * comes from the catalog (cheapest supplier price, the equipment rate, the
 * tool cost, an employee's hourly rate) and the markup from the shop's
 * default for that line type. An explicit unit_cost or markup_percent is
 * accepted as an override - exactly as typing over the field is in the form
 * - and a line with no catalog link and no cost starts at $0 for the owner
 * to price.
 *
 * Every id is checked against this shop's own records, and a labor line is
 * either an employee or an outsourced service, never both (the rule the web
 * form enforces).
 */
class QuoteLineResolver
{
    public const MAX_LINES = 50;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>> Rows for QuoteLineItem::createMany().
     *
     * @throws ValidationException
     */
    public function resolve(Shop $shop, array $lines): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('shop_id', $shop->id);

        $validated = Validator::make(['lines' => $lines], [
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.type' => ['required', Rule::enum(LineItemType::class)],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'lines.*.markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'lines.*.material_id' => ['nullable', $exists('materials')],
            'lines.*.service_id' => ['nullable', $exists('services')],
            'lines.*.business_partner_id' => ['nullable', $exists('business_partners')],
            'lines.*.material_dimension' => ['nullable', 'string', 'max:100'],
            'lines.*.equipment_id' => ['nullable', $exists('equipment')],
            'lines.*.tool_id' => ['nullable', $exists('tools')],
            'lines.*.user_id' => ['nullable', Rule::exists('shop_user', 'user_id')->where('shop_id', $shop->id)],
        ], [], ['lines.*.description' => 'description'])->after(function ($validator) use ($lines) {
            foreach ($lines as $i => $line) {
                if (filled($line['user_id'] ?? null) && filled($line['service_id'] ?? null)) {
                    $validator->errors()->add("lines.{$i}.user_id", 'A line item cannot have both an employee and an outsourced service.');
                }
            }
        })->validate();

        return collect($validated['lines'])->map(fn (array $line, int $index) => $this->row($shop, $line, $index))->all();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function row(Shop $shop, array $line, int $index): array
    {
        $type = LineItemType::from($line['type']);

        $row = [
            'type' => $type->value,
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_cost' => 0,
            'markup_percent' => $line['markup_percent'] ?? MarkupDefault::forType($type),
            'override_total' => null,
            'material_id' => $line['material_id'] ?? null,
            'service_id' => $line['service_id'] ?? null,
            'business_partner_id' => $line['business_partner_id'] ?? null,
            'material_dimension' => $line['material_dimension'] ?? null,
            'user_id' => $line['user_id'] ?? null,
            'equipment_id' => $line['equipment_id'] ?? null,
            'tool_id' => $line['tool_id'] ?? null,
            'sort_order' => $index,
        ];

        // Only the links that make sense for this type are kept.
        $row = match ($type) {
            LineItemType::Material => [...$row, ...$this->fromMaterial($row), 'service_id' => null, 'user_id' => null, 'equipment_id' => null, 'tool_id' => null],
            LineItemType::Labor => [...$row, ...$this->fromLabor($shop, $row), 'material_id' => null, 'material_dimension' => null, 'equipment_id' => null, 'tool_id' => null],
            LineItemType::Equipment => [...$row, ...$this->fromEquipment($row), 'material_id' => null, 'service_id' => null, 'business_partner_id' => null, 'material_dimension' => null, 'user_id' => null, 'tool_id' => null],
            LineItemType::Tooling => [...$row, ...$this->fromTool($row), 'material_id' => null, 'service_id' => null, 'business_partner_id' => null, 'material_dimension' => null, 'user_id' => null, 'equipment_id' => null],
        };

        // An explicit cost always wins over the catalog's.
        if (array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null) {
            $row['unit_cost'] = $line['unit_cost'];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fromMaterial(array $row): array
    {
        $material = $row['material_id'] ? Material::query()->with('businessPartners')->find($row['material_id']) : null;

        if (! $material) {
            return [];
        }

        $offers = $material->businessPartners
            ->when($row['business_partner_id'], fn ($offers, $partner) => $offers->where('id', $partner))
            ->when($row['material_dimension'], fn ($offers, $size) => $offers->filter(fn ($p) => $p->pivot->dimension === $size));

        $best = $offers->sortBy(fn ($p) => (float) $p->pivot->price)->first();

        return $best ? [
            'business_partner_id' => $best->id,
            'material_dimension' => $best->pivot->dimension,
            'unit_cost' => (float) $best->pivot->price,
        ] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fromLabor(Shop $shop, array $row): array
    {
        if ($row['user_id']) {
            $rate = $shop->members()->whereKey($row['user_id'])->first()?->pivot->hourly_rate;

            return ['service_id' => null, 'business_partner_id' => null, 'unit_cost' => $rate !== null ? (float) $rate : 0];
        }

        $service = $row['service_id'] ? Service::query()->with('businessPartners')->find($row['service_id']) : null;

        if (! $service) {
            return [];
        }

        $offers = $service->businessPartners
            ->when($row['business_partner_id'], fn ($offers, $partner) => $offers->where('id', $partner));

        $best = $offers->sortBy(fn ($p) => (float) $p->pivot->price)->first();

        // The line card needs the service *and* a supplier to price it, so a
        // service nobody supplies stays an unlinked labor line.
        return $best
            ? ['business_partner_id' => $best->id, 'unit_cost' => (float) $best->pivot->price]
            : ['service_id' => null, 'business_partner_id' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fromEquipment(array $row): array
    {
        $equipment = $row['equipment_id'] ? Equipment::query()->find($row['equipment_id']) : null;

        return $equipment ? ['unit_cost' => (float) $equipment->rate] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function fromTool(array $row): array
    {
        $tool = $row['tool_id'] ? Tool::query()->find($row['tool_id']) : null;

        return $tool ? ['unit_cost' => (float) $tool->cost] : [];
    }
}

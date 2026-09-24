<?php

namespace App\Http\Controllers;

use App\Enums\LineItemType;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\MarkupDefault;
use App\Models\Material;
use App\Models\Quote;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Quotes/Index', [
            'quotes' => Quote::query()
                ->with(['customer:id,name', 'lineItems'])
                ->latest()
                ->get()
                ->map(fn (Quote $quote) => [
                    'id' => $quote->id,
                    'customer' => $quote->customer->name,
                    'status' => $quote->status->value,
                    'total' => $quote->total,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Quotes/Create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            ...$this->catalogProps(),
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $quote = DB::transaction(function () use ($request) {
            $quote = Quote::create($request->safe()->only(['customer_id', 'status', 'notes']));

            $this->syncLineItems($quote, $request->validated('line_items'));

            return $quote;
        });

        return redirect()->route('quotes.edit', $quote);
    }

    public function edit(Quote $quote): Response
    {
        return Inertia::render('Quotes/Edit', [
            'quote' => [
                'id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'status' => $quote->status->value,
                'notes' => $quote->notes,
                'line_items' => $quote->lineItems->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type->value,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'markup_percent' => (float) $item->markup_percent,
                    'override_total' => $item->override_total !== null ? (float) $item->override_total : null,
                    'material_id' => $item->material_id,
                    'service_id' => $item->service_id,
                    'business_partner_id' => $item->business_partner_id,
                    'user_id' => $item->user_id,
                    'equipment_id' => $item->equipment_id,
                ]),
            ],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            ...$this->catalogProps(),
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        DB::transaction(function () use ($request, $quote) {
            $quote->update($request->safe()->only(['customer_id', 'status', 'notes']));

            $this->syncLineItems($quote, $request->validated('line_items'));
        });

        return redirect()->route('quotes.edit', $quote);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();

        return redirect()->route('quotes.index');
    }

    /**
     * Shared data the quote builder needs to offer catalog-backed line items:
     * which materials/services each business partner supplies (with price),
     * equipment costs, employees with their hourly rate, and this shop's
     * default markup per line-item type.
     *
     * @return array<string, mixed>
     */
    private function catalogProps(): array
    {
        $shop = Auth::user()->currentShop;

        return [
            'materials' => Material::query()->with('businessPartners:id,name')->orderBy('name')->get()
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'partners' => $material->businessPartners->map(fn ($partner) => [
                        'business_partner_id' => $partner->id,
                        'name' => $partner->name,
                        'price' => (float) $partner->pivot->price,
                    ]),
                ]),
            'services' => Service::query()->with('businessPartners:id,name')->orderBy('name')->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'partners' => $service->businessPartners->map(fn ($partner) => [
                        'business_partner_id' => $partner->id,
                        'name' => $partner->name,
                        'price' => (float) $partner->pivot->price,
                    ]),
                ]),
            'equipmentList' => Equipment::query()->orderBy('name')->get(['id', 'name', 'billing_method', 'rate'])
                ->map(fn (Equipment $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'billing_method' => $item->billing_method->value,
                    'rate' => (float) $item->rate,
                ]),
            'employees' => $shop->members()->get()->map(fn ($member) => [
                'user_id' => $member->id,
                'name' => $member->name,
                'hourly_rate' => $member->pivot->hourly_rate !== null ? (float) $member->pivot->hourly_rate : null,
            ]),
            'markupDefaults' => collect(LineItemType::cases())->mapWithKeys(
                fn (LineItemType $type) => [$type->value => MarkupDefault::forType($type)],
            ),
        ];
    }

    /**
     * Replace a quote's line items wholesale - simpler and safer than diffing
     * added/edited/removed rows for what is, for now, a small per-quote list.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function syncLineItems(Quote $quote, array $lineItems): void
    {
        $quote->lineItems()->delete();

        $quote->lineItems()->createMany(array_map(
            fn (array $item, int $index): array => [...$item, 'sort_order' => $index],
            $lineItems,
            array_keys($lineItems),
        ));
    }
}

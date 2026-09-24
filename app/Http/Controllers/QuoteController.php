<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
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
                ]),
            ],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
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

<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\LineItemType;
use App\Enums\QuoteStatus;
use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\FeatureSetting;
use App\Models\MarkupDefault;
use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteHistoryEntry;
use App\Models\Service;
use App\Models\Tool;
use App\Support\Attachments;
use App\Support\QuoteHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Quotes;
    }

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
            $quote = Quote::create([
                ...$request->safe()->only(['customer_id', 'notes', 'due_date']),
                'status' => QuoteStatus::Draft,
            ]);

            $this->syncLineItems($quote, $request->validated('line_items'));

            foreach ($request->file('attachments', []) as $file) {
                $quote->addMedia($file)->toMediaCollection('attachments');
            }

            return $quote;
        });

        QuoteHistory::log($quote, 'created', 'Quote created');

        return redirect()->route('quotes.edit', $quote);
    }

    public function edit(Quote $quote): Response
    {
        return Inertia::render('Quotes/Edit', [
            'quote' => [
                'id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'status' => $quote->status->value,
                'is_archived' => $quote->is_archived,
                'job_id' => $quote->job?->id,
                'customer_email' => $quote->customer->email,
                'emailed_at' => $quote->emailed_at?->toIso8601String(),
                'email_failed_at' => $quote->email_failed_at?->toIso8601String(),
                'customer_locked' => $quote->customer_locked,
                'emailing_enabled' => Auth::user()->currentShop->quoteEmailingEnabled(),
                'send_skipped_reason' => $quote->send_skipped_reason,
                'notes' => $quote->notes,
                'due_date' => $quote->due_date?->toDateString(),
                'attachments' => Attachments::props($quote->attachments(), 'quotes.attachments.show', $quote),
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
                    'material_dimension' => $item->material_dimension,
                    'user_id' => $item->user_id,
                    'equipment_id' => $item->equipment_id,
                    'tool_id' => $item->tool_id,
                ]),
            ],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'history' => $this->historyProps($quote),
            ...$this->catalogProps(),
        ]);
    }

    /**
     * The quote's change log, or null for a shop with no claim to it - the
     * table isn't even queried then. A shop that lost the feature (trial
     * ended, add-on cancelled) still sees what was recorded, read-only, but
     * nothing new is written. `upsell` carries the add-on price when there's
     * nothing to show, so the page can offer it.
     *
     * @return array<string, mixed>
     */
    private function historyProps(Quote $quote): array
    {
        $shop = Auth::user()->currentShop;
        $entitled = $shop->hasFeature(Feature::QuoteHistory);

        $entries = ($entitled || $shop->canViewFeature(Feature::QuoteHistory))
            ? $quote->historyEntries()->with('user:id,name')->get()
            : collect();

        if (! $entitled && $entries->isEmpty()) {
            return ['entries' => null, 'read_only' => false, 'price_cents' => FeatureSetting::priceFor(Feature::QuoteHistory)];
        }

        return [
            'entries' => $entries->map(fn (QuoteHistoryEntry $entry) => [
                'id' => $entry->id,
                'summary' => $entry->summary,
                'changes' => $entry->changes ?? [],
                'user' => $entry->user?->name,
                'created_at' => $entry->created_at->toIso8601String(),
            ])->values(),
            'read_only' => ! $entitled,
            'price_cents' => null,
        ];
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $customerWas = $quote->customer_id;

        // Entitled shops only: snapshot first so the save can be diffed.
        $before = QuoteHistory::enabled() ? QuoteHistory::snapshot($quote) : null;

        DB::transaction(function () use ($request, $quote) {
            $quote->update($request->safe()->only(['customer_id', 'notes', 'due_date']));

            $this->syncLineItems($quote, $request->validated('line_items'));
        });

        if ($before !== null) {
            QuoteHistory::recordSave(
                $quote,
                $before,
                $customerWas !== $quote->customer_id && $quote->email_failed_at !== null
                    ? 'Saved changes - customer corrected after the email failed to deliver'
                    : 'Saved changes',
            );
        }

        return redirect()->route('quotes.edit', $quote);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        // A converted quote is the job's price record - archive it instead.
        if ($quote->job()->exists()) {
            return back()->with('error', "This quote became job {$quote->job->job_number}, so it can't be deleted. Archive it instead.");
        }

        $quote->delete();

        return redirect()->route('quotes.index');
    }

    /**
     * Shared data the quote builder needs to offer catalog-backed line items:
     * which materials/services each business partner supplies (with price),
     * equipment costs, tooling (with what it cuts), employees with their
     * hourly rate, and this shop's
     * default markup per line-item type.
     *
     * @return array<string, mixed>
     */
    private function catalogProps(): array
    {
        $shop = Auth::user()->currentShop;

        return [
            'materials' => Material::query()->with('businessPartners:id,name', 'materialType:id,name')->orderBy('name')->get()
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'material_type' => $material->materialType ? ['id' => $material->materialType->id, 'name' => $material->materialType->name] : null,
                    'partners' => $material->businessPartners->map(fn ($partner) => [
                        'business_partner_id' => $partner->id,
                        'name' => $partner->name,
                        'dimension' => $partner->pivot->dimension,
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
            'tools' => Tool::query()->with('materialTypes:id,name')->orderBy('name')->get()
                ->map(fn (Tool $tool) => [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'cuts' => $tool->cutsLabel(),
                    'cut_type_ids' => $tool->materialTypes->pluck('id')->all(),
                    'cost' => (float) $tool->cost,
                    'unit' => $tool->unit,
                ]),
            'employees' => $shop->members()->get()->map(fn ($member) => [
                'user_id' => $member->id,
                'name' => $member->name,
                'hourly_rate' => $member->pivot->hourly_rate !== null ? (float) $member->pivot->hourly_rate : null,
            ]),
            'markupDefaults' => collect(LineItemType::cases())->mapWithKeys(
                fn (LineItemType $type) => [$type->value => MarkupDefault::forType($type)],
            ),
            // Whether "Draft from a customer email" can work right now:
            // entitled (trial/plan) and a key saved. Two flags so the form
            // can say which one is missing.
            'ai' => [
                'available' => $shop->hasFeature(Feature::AiAssist),
                'configured' => (bool) $shop->aiSetting?->hasKey(),
            ],
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

<?php

namespace App\Http\Controllers;

use App\Enums\BusinessPartnerType;
use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreBusinessPartnerRequest;
use App\Http\Requests\UpdateBusinessPartnerRequest;
use App\Models\Business;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Service;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessPartnerController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::BusinessPartners;
    }

    public function index(): Response
    {
        return Inertia::render('BusinessPartners/Index', [
            'businessPartners' => BusinessPartner::query()->with('media')->orderBy('name')->get(['id', 'name', 'email', 'phone', 'type'])
                ->map(fn (BusinessPartner $partner) => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                    'type' => $partner->type->value,
                    'type_label' => $partner->type->label(),
                    'logo_url' => $partner->logoUrl(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('BusinessPartners/Create', [
            'typeOptions' => BusinessPartnerType::options(),
            'materials' => Material::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreBusinessPartnerRequest $request): RedirectResponse
    {
        $partner = DB::transaction(function () use ($request) {
            $partner = BusinessPartner::create($request->safe()->only(['name', 'email', 'phone', 'address', 'notes', 'type']));

            $this->syncCapabilities($partner, $request->validated('materials', []), $request->validated('services', []));

            Images::sync($partner, $request, 'logo', 'logo');

            return $partner;
        });

        return redirect()->route('business-partners.edit', $partner);
    }

    public function edit(BusinessPartner $businessPartner): Response
    {
        return Inertia::render('BusinessPartners/Edit', [
            'typeOptions' => BusinessPartnerType::options(),
            'customerId' => $businessPartner->customer?->id,
            'businessPartner' => [
                'type' => $businessPartner->type->value,
                'id' => $businessPartner->id,
                'name' => $businessPartner->name,
                'email' => $businessPartner->email,
                'phone' => $businessPartner->phone,
                'address' => $businessPartner->address,
                'notes' => $businessPartner->notes,
                'logo_url' => $businessPartner->logoUrl(),
                'materials' => $businessPartner->materials->map(fn (Material $material) => [
                    'material_id' => $material->id,
                    'name' => $material->name,
                    'dimension' => $material->pivot->dimension,
                    'price' => (float) $material->pivot->price,
                ]),
                'services' => $businessPartner->services->map(fn (Service $service) => [
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'price' => (float) $service->pivot->price,
                ]),
            ],
            'materials' => Material::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateBusinessPartnerRequest $request, BusinessPartner $businessPartner): RedirectResponse
    {
        DB::transaction(function () use ($request, $businessPartner) {
            $businessPartner->update($request->safe()->only(['name', 'email', 'phone', 'address', 'notes', 'type']));

            $this->syncCapabilities($businessPartner, $request->validated('materials', []), $request->validated('services', []));

            Images::sync($businessPartner, $request, 'logo', 'logo');
        });

        return redirect()->route('business-partners.edit', $businessPartner);
    }

    public function destroy(BusinessPartner $businessPartner): RedirectResponse
    {
        $businessPartner->delete();

        return redirect()->route('business-partners.index');
    }

    /**
     * Give this partner's business a customer role too. If a customer with
     * the same name already exists it is linked rather than duplicated (its
     * own details are left as they are); otherwise one is created from the
     * partner's name and contact details and logo. Notes are not copied.
     */
    public function makeCustomer(BusinessPartner $businessPartner): RedirectResponse
    {
        if ($businessPartner->customer) {
            return redirect()->route('customers.edit', $businessPartner->customer)
                ->with('status', "{$businessPartner->name} is already a customer.");
        }

        $existing = Customer::query()
            ->whereNull('business_id')
            ->whereRaw('lower(name) = ?', [mb_strtolower($businessPartner->name)])
            ->first();

        $customer = DB::transaction(function () use ($businessPartner, $existing) {
            $business = $businessPartner->business ?? Business::create();

            $customer = $existing ?? Customer::create(
                $businessPartner->only(BusinessPartner::SHARED_FIELDS),
            );

            if (! $existing) {
                $businessPartner->getFirstMedia('logo')?->copy($customer, 'logo');
            }

            $customer->forceFill(['business_id' => $business->id])->saveQuietly();
            $businessPartner->forceFill(['business_id' => $business->id])->saveQuietly();

            return $customer;
        });

        return redirect()->route('customers.edit', $customer)->with('status', $existing
            ? "Linked {$businessPartner->name} to the existing customer of the same name."
            : "{$businessPartner->name} is now also a customer.");
    }

    /**
     * @param  array<int, array{material_id: int, dimension?: string|null, price: float}>  $materials
     * @param  array<int, array{service_id: int, price: float}>  $services
     */
    private function syncCapabilities(BusinessPartner $partner, array $materials, array $services): void
    {
        // A material can appear several times (one row per size), which
        // sync() can't express - replace the whole set instead.
        $partner->materials()->detach();
        foreach ($materials as $row) {
            $partner->materials()->attach($row['material_id'], [
                'price' => $row['price'],
                'dimension' => filled($row['dimension'] ?? null) ? trim($row['dimension']) : null,
            ]);
        }

        $partner->services()->sync(collect($services)->mapWithKeys(
            fn (array $row) => [$row['service_id'] => ['price' => $row['price']]],
        ));
    }
}

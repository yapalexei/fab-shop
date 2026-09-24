<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreBusinessPartnerRequest;
use App\Http\Requests\UpdateBusinessPartnerRequest;
use App\Models\BusinessPartner;
use App\Models\Material;
use App\Models\Service;
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
            'businessPartners' => BusinessPartner::query()->orderBy('name')->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('BusinessPartners/Create', [
            'materials' => Material::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreBusinessPartnerRequest $request): RedirectResponse
    {
        $partner = DB::transaction(function () use ($request) {
            $partner = BusinessPartner::create($request->safe()->only(['name', 'email', 'phone', 'address', 'notes']));

            $this->syncCapabilities($partner, $request->validated('materials', []), $request->validated('services', []));

            return $partner;
        });

        return redirect()->route('business-partners.edit', $partner);
    }

    public function edit(BusinessPartner $businessPartner): Response
    {
        return Inertia::render('BusinessPartners/Edit', [
            'businessPartner' => [
                'id' => $businessPartner->id,
                'name' => $businessPartner->name,
                'email' => $businessPartner->email,
                'phone' => $businessPartner->phone,
                'address' => $businessPartner->address,
                'notes' => $businessPartner->notes,
                'materials' => $businessPartner->materials->map(fn (Material $material) => [
                    'material_id' => $material->id,
                    'name' => $material->name,
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
            $businessPartner->update($request->safe()->only(['name', 'email', 'phone', 'address', 'notes']));

            $this->syncCapabilities($businessPartner, $request->validated('materials', []), $request->validated('services', []));
        });

        return redirect()->route('business-partners.edit', $businessPartner);
    }

    public function destroy(BusinessPartner $businessPartner): RedirectResponse
    {
        $businessPartner->delete();

        return redirect()->route('business-partners.index');
    }

    /**
     * @param  array<int, array{material_id: int, price: float}>  $materials
     * @param  array<int, array{service_id: int, price: float}>  $services
     */
    private function syncCapabilities(BusinessPartner $partner, array $materials, array $services): void
    {
        $partner->materials()->sync(collect($materials)->mapWithKeys(
            fn (array $row) => [$row['material_id'] => ['price' => $row['price']]],
        ));

        $partner->services()->sync(collect($services)->mapWithKeys(
            fn (array $row) => [$row['service_id'] => ['price' => $row['price']]],
        ));
    }
}

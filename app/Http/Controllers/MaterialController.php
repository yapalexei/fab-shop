<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Models\MaterialType;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Materials;
    }

    public function index(): Response
    {
        return Inertia::render('Materials/Index', [
            'materials' => Material::query()->with('materialType:id,name', 'media')->orderBy('name')->get()
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'material_type' => $material->materialType?->name,
                    'image_url' => $material->imageUrl(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Materials/Create', [
            'materialTypes' => $this->materialTypeOptions(),
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        $material = Material::create($request->safe()->except(['image', 'remove_image']));

        Images::sync($material, $request, 'image', 'image');

        return redirect()->route('materials.index');
    }

    public function edit(Material $material): Response
    {
        return Inertia::render('Materials/Edit', [
            'material' => [...$material->toArray(), 'image_url' => $material->imageUrl()],
            'materialTypes' => $this->materialTypeOptions(),
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $material->update($request->safe()->except(['image', 'remove_image']));

        Images::sync($material, $request, 'image', 'image');

        return redirect()->route('materials.index');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()->route('materials.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function materialTypeOptions(): array
    {
        return MaterialType::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name'])->toArray();
    }
}

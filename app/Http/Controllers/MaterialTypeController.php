<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreMaterialTypeRequest;
use App\Http\Requests\UpdateMaterialTypeRequest;
use App\Models\MaterialType;
use App\Support\MaterialTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shop's material types on one page: the built-ins every shop has
 * (fixed) plus the shop's own custom ones, managed inline.
 */
class MaterialTypeController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::MaterialTypes;
    }

    public function index(): Response
    {
        $types = MaterialType::query()
            ->withCount(['tools', 'materials'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('MaterialTypes/Index', [
            'types' => $types->map(fn (MaterialType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'is_default' => $type->is_default,
                'tools_count' => $type->tools_count,
                'materials_count' => $type->materials_count,
            ]),
            'customCount' => $types->where('is_default', false)->count(),
            'customLimit' => MaterialTypes::customLimit(),
        ]);
    }

    public function store(StoreMaterialTypeRequest $request): RedirectResponse
    {
        MaterialType::create($request->validated());

        return back()->with('status', "Added {$request->validated('name')}.");
    }

    public function update(UpdateMaterialTypeRequest $request, MaterialType $materialType): RedirectResponse
    {
        $materialType->update($request->validated());

        return back()->with('status', 'Material type renamed.');
    }

    public function destroy(MaterialType $materialType): RedirectResponse
    {
        if ($materialType->is_default) {
            return back()->with('error', "{$materialType->name} is built in and can't be deleted.");
        }

        $tools = $materialType->tools()->count();
        $materials = $materialType->materials()->count();

        if ($tools + $materials > 0) {
            return back()->with('error', "{$materialType->name} is still used by {$tools} tool(s) and {$materials} material(s) - change those first.");
        }

        $materialType->delete();

        return back()->with('status', "Deleted {$materialType->name}.");
    }
}

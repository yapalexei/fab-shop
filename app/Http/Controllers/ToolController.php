<?php

namespace App\Http\Controllers;

use App\Enums\CuttableMaterial;
use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreToolRequest;
use App\Http\Requests\UpdateToolRequest;
use App\Models\Tool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class ToolController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Tooling;
    }

    public function index(): Response
    {
        return Inertia::render('Tools/Index', [
            'tools' => Tool::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Tool $tool) => [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'cuts' => $tool->cuts->map(fn (CuttableMaterial $m) => $m->value)->all(),
                    'cost' => (float) $tool->cost,
                    'unit' => $tool->unit,
                ]),
            'materialOptions' => CuttableMaterial::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tools/Create', [
            'materialOptions' => CuttableMaterial::options(),
        ]);
    }

    public function store(StoreToolRequest $request): RedirectResponse
    {
        Tool::create($request->validated());

        return redirect()->route('tools.index');
    }

    public function edit(Tool $tool): Response
    {
        return Inertia::render('Tools/Edit', [
            'tool' => [
                'id' => $tool->id,
                'name' => $tool->name,
                'cuts' => $tool->cuts->map(fn (CuttableMaterial $m) => $m->value)->all(),
                'cost' => (float) $tool->cost,
                'unit' => $tool->unit,
                'notes' => $tool->notes,
            ],
            'materialOptions' => CuttableMaterial::options(),
        ]);
    }

    public function update(UpdateToolRequest $request, Tool $tool): RedirectResponse
    {
        $tool->update($request->validated());

        return redirect()->route('tools.index');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        $tool->delete();

        return redirect()->route('tools.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreToolRequest;
use App\Http\Requests\UpdateToolRequest;
use App\Models\MaterialType;
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
                ->with('materialTypes:id')
                ->orderBy('name')
                ->get()
                ->map(fn (Tool $tool) => [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'cuts' => $tool->materialTypes->pluck('id')->all(),
                    'cost' => (float) $tool->cost,
                    'unit' => $tool->unit,
                ]),
            'materialOptions' => $this->materialOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tools/Create', [
            'materialOptions' => $this->materialOptions(),
        ]);
    }

    public function store(StoreToolRequest $request): RedirectResponse
    {
        $tool = Tool::create($request->safe()->except('cuts'));
        $tool->materialTypes()->sync($request->validated('cuts'));

        return redirect()->route('tools.index');
    }

    public function edit(Tool $tool): Response
    {
        return Inertia::render('Tools/Edit', [
            'tool' => [
                'id' => $tool->id,
                'name' => $tool->name,
                'cuts' => $tool->materialTypes()->pluck('material_types.id')->all(),
                'cost' => (float) $tool->cost,
                'unit' => $tool->unit,
                'notes' => $tool->notes,
            ],
            'materialOptions' => $this->materialOptions(),
        ]);
    }

    public function update(UpdateToolRequest $request, Tool $tool): RedirectResponse
    {
        $tool->update($request->safe()->except('cuts'));
        $tool->materialTypes()->sync($request->validated('cuts'));

        return redirect()->route('tools.index');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        $tool->delete();

        return redirect()->route('tools.index');
    }

    /**
     * The shop's material types, for the "Cuts" checkboxes and the filter.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function materialOptions(): array
    {
        return MaterialType::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (MaterialType $type) => ['value' => $type->id, 'label' => $type->name])
            ->all();
    }
}

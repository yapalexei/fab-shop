<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreToolRequest;
use App\Http\Requests\UpdateToolRequest;
use App\Models\MaterialType;
use App\Models\Tool;
use App\Support\Attachments;
use App\Support\Images;
use App\Support\SupplierPrices;
use App\Support\ToolModels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ToolController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    /** Request fields that aren't columns on `tools`. */
    private const NON_COLUMNS = ['cuts', 'image', 'remove_image', 'model', 'remove_model', 'mark_price_checked'];

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Tooling;
    }

    public function index(): Response
    {
        return Inertia::render('Tools/Index', [
            'tools' => Tool::query()
                ->with(['materialTypes:id', 'media'])
                ->orderBy('name')
                ->get()
                ->map(fn (Tool $tool) => [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'cuts' => $tool->materialTypes->pluck('id')->all(),
                    'cost' => (float) $tool->cost,
                    'unit' => $tool->unit,
                    'image_url' => $tool->imageUrl(),
                ]),
            'materialOptions' => $this->materialOptions(),
        ]);
    }

    public function create(): Response
    {
        $shop = auth()->user()->currentShop;

        return Inertia::render('Tools/Create', [
            'materialOptions' => $this->materialOptions(),
            // Whether "Fill from a supplier page" can work right now.
            'ai' => [
                'available' => $shop->hasFeature(Feature::AiAssist),
                'configured' => (bool) $shop->aiSetting?->hasKey(),
            ],
        ]);
    }

    public function store(StoreToolRequest $request): RedirectResponse
    {
        $tool = Tool::create($request->safe()->except(self::NON_COLUMNS));
        $tool->forceFill(['price_checked_at' => now()])->save();
        $tool->materialTypes()->sync($request->validated('cuts'));
        $this->syncFiles($tool, $request);

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
                'part_number' => $tool->part_number,
                'source_url' => $tool->source_url,
                'price_checked_at' => SupplierPrices::iso($tool->price_checked_at),
                'image_url' => $tool->imageUrl(),
                'model' => ToolModels::props($tool),
            ],
            'materialOptions' => $this->materialOptions(),
        ]);
    }

    public function update(UpdateToolRequest $request, Tool $tool): RedirectResponse
    {
        $tool->update($request->safe()->except(self::NON_COLUMNS));

        // The price counts as checked when it changed or the owner says so.
        if ($tool->wasChanged('cost') || $request->boolean('mark_price_checked')) {
            $tool->forceFill(['price_checked_at' => now()])->save();
        }

        $tool->materialTypes()->sync($request->validated('cuts'));
        $this->syncFiles($tool, $request);

        return redirect()->route('tools.index');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        $tool->delete();

        return redirect()->route('tools.index');
    }

    public function image(Request $request, Tool $tool): StreamedResponse
    {
        abort_unless(Gate::any(['tooling.view', 'quotes.view', 'jobs.view']), 403);

        return Images::respond($request, $tool, 'image');
    }

    public function model(Request $request, Tool $tool): StreamedResponse
    {
        abort_unless(Gate::allows('tooling.view'), 403);

        return Attachments::respond($request, ToolModels::media($tool));
    }

    private function syncFiles(Tool $tool, Request $request): void
    {
        Images::sync($tool, $request, 'image', 'image');
        ToolModels::sync($tool, $request);
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

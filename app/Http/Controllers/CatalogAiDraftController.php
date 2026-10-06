<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftCatalogItemWithAiRequest;
use App\Models\Material;
use App\Models\MaterialType;
use App\Services\Ai\AiException;
use App\Services\Ai\CatalogItemDrafter;
use Illuminate\Http\JsonResponse;

/**
 * Suggests the fields of a new tool, material or supplier price from text pasted off a
 * supplier's product page. Plain JSON, saves nothing: the form fills in and
 * the owner reviews it before saving. No page is fetched here - the owner
 * pastes the text - so there is no outbound request to an address they chose.
 */
class CatalogAiDraftController extends Controller
{
    public function __invoke(DraftCatalogItemWithAiRequest $request, string $kind, CatalogItemDrafter $drafter): JsonResponse
    {
        $user = $request->user();
        $shop = $user->currentShop;

        // What the model may choose from - ids and names only, never costs.
        // Tenant scoping comes from the models' global scope.
        $truncated = false;

        if ($kind === 'supplier-prices') {
            $cap = config('ai.max_catalog_items');
            $materials = Material::query()->orderBy('name')->limit($cap + 1)->get(['id', 'name']);
            $truncated = $materials->count() > $cap;
            $context = $materials->take($cap)->map(fn (Material $material) => ['id' => $material->id, 'name' => $material->name])->values()->all();
        } else {
            $context = MaterialType::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (MaterialType $type) => ['id' => $type->id, 'name' => $type->name])->all();
        }

        try {
            $draft = $drafter->draft(
                $shop,
                $user->id,
                match ($kind) {
                    'tools' => CatalogItemDrafter::TOOL,
                    'supplier-prices' => CatalogItemDrafter::PRICE,
                    default => CatalogItemDrafter::MATERIAL,
                },
                $request->validated('text'),
                $context,
            );
        } catch (AiException $e) {
            // The message is written to be shown to the owner as-is.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($kind !== 'materials' && $draft['found']) {
            $draft['fields']['source_url'] = $request->validated('source_url');
        }

        if ($truncated) {
            $draft['checks'][] = 'Your materials list is large, so only part of it was considered for the match.';
        }

        return response()->json($draft);
    }
}

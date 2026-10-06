<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftCatalogItemWithAiRequest;
use App\Models\MaterialType;
use App\Services\Ai\AiException;
use App\Services\Ai\CatalogItemDrafter;
use Illuminate\Http\JsonResponse;

/**
 * Suggests the fields of a new tool or material from text pasted off a
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

        // Tenant scoping comes from MaterialType's global scope.
        $types = MaterialType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (MaterialType $type) => ['id' => $type->id, 'name' => $type->name])->all();

        try {
            $draft = $drafter->draft(
                $shop,
                $user->id,
                $kind === 'tools' ? CatalogItemDrafter::TOOL : CatalogItemDrafter::MATERIAL,
                $request->validated('text'),
                $types,
            );
        } catch (AiException $e) {
            // The message is written to be shown to the owner as-is.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($kind === 'tools' && $draft['found']) {
            $draft['fields']['source_url'] = $request->validated('source_url');
        }

        return response()->json($draft);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftQuoteWithAiRequest;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Service;
use App\Models\Tool;
use App\Services\Ai\AiException;
use App\Services\Ai\QuoteDrafter;
use Illuminate\Http\JsonResponse;

/**
 * Suggests quote lines from a pasted customer request. Returns plain JSON
 * (like the CSV import status endpoint) because the quote form stays open
 * and just appends the suggestions - nothing is saved here, and the owner
 * reviews every line before saving the quote.
 */
class QuoteAiDraftController extends Controller
{
    public function __invoke(DraftQuoteWithAiRequest $request, QuoteDrafter $drafter): JsonResponse
    {
        $user = $request->user();
        $shop = $user->currentShop;

        [$catalog, $truncated] = $this->catalog();

        try {
            $draft = $drafter->draft($shop, $user->id, $request->validated('message'), $catalog);
        } catch (AiException $e) {
            // The message is written to be shown to the owner as-is.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([...$draft, 'catalog_truncated' => $truncated]);
    }

    /**
     * What the model may choose from: id and name (plus a little context)
     * for each of the current shop's catalog items - never costs or
     * supplier details. Tenant scoping comes from the models' global scope.
     *
     * @return array{0: array<string, array<int, array<string, mixed>>>, 1: bool}
     */
    private function catalog(): array
    {
        $cap = config('ai.max_catalog_items');
        $truncated = false;

        $take = function ($query, callable $map) use ($cap, &$truncated): array {
            $rows = $query->limit($cap + 1)->get();

            if ($rows->count() > $cap) {
                $truncated = true;
                $rows = $rows->take($cap);
            }

            return $rows->map($map)->values()->all();
        };

        $catalog = [
            'materials' => $take(
                Material::query()->with('materialType:id,name')->orderBy('name'),
                fn (Material $material) => ['id' => $material->id, 'name' => $material->name, 'type' => $material->materialType?->name],
            ),
            'services' => $take(
                Service::query()->orderBy('name'),
                fn (Service $service) => ['id' => $service->id, 'name' => $service->name],
            ),
            'equipment' => $take(
                Equipment::query()->orderBy('name'),
                fn (Equipment $item) => ['id' => $item->id, 'name' => $item->name],
            ),
            'tools' => $take(
                Tool::query()->with('materialTypes:id,name')->orderBy('name'),
                fn (Tool $tool) => ['id' => $tool->id, 'name' => $tool->name, 'cuts' => $tool->cutsLabel()],
            ),
        ];

        return [$catalog, $truncated];
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Support\SampleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The "Load sample ..." dialog: lists, then adds the chosen starter material types, materials,
 * services or equipment from SampleCatalog to the current shop. Needs the
 * `create` permission for whichever resource it adds to, and only ever adds
 * what's missing.
 */
class SampleDataController extends Controller
{
    private const RESOURCES = [
        'material-types' => [ShopResource::MaterialTypes, 'material types'],
        'materials' => [ShopResource::Materials, 'materials'],
        'services' => [ShopResource::Services, 'services'],
        'equipment' => [ShopResource::Equipment, 'equipment'],
    ];

    /**
     * What the dialog can offer: every sample item and whether the shop
     * already has it. Plain JSON - the dialog fetches it when opened.
     */
    public function index(string $resource): JsonResponse
    {
        $this->authorizeResource($resource);

        return response()->json(['items' => SampleCatalog::options($resource, Auth::user()->currentShop)]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $label = $this->authorizeResource($resource);

        $names = collect(SampleCatalog::catalog($resource))->pluck('name');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', Rule::in($names)],
        ], ['items.required' => 'Pick at least one item to add.', 'items.min' => 'Pick at least one item to add.']);

        $added = SampleCatalog::load($resource, Auth::user()->currentShop, $validated['items']);

        return back()->with('status', $added > 0
            ? "Added {$added} sample {$label}."
            : "You already have the {$label} you picked.");
    }

    /**
     * 404 for an unknown resource, 403 without the create permission.
     *
     * @return string the plural label, for messages
     */
    private function authorizeResource(string $resource): string
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        [$shopResource, $label] = self::RESOURCES[$resource];

        Gate::authorize($shopResource->permission('create'));

        return $label;
    }
}

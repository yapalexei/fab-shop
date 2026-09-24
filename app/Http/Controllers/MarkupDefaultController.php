<?php

namespace App\Http\Controllers;

use App\Enums\LineItemType;
use App\Http\Requests\UpdateMarkupDefaultsRequest;
use App\Models\MarkupDefault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MarkupDefaultController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('MarkupDefaults/Index', [
            'defaults' => collect(LineItemType::cases())->map(fn (LineItemType $type) => [
                'line_item_type' => $type->value,
                'default_markup_percent' => MarkupDefault::forType($type),
            ]),
            'canManage' => Gate::allows('manage-shop'),
        ]);
    }

    public function update(UpdateMarkupDefaultsRequest $request): RedirectResponse
    {
        $shopId = Auth::user()->current_shop_id;

        foreach ($request->validated('defaults') as $row) {
            MarkupDefault::query()->updateOrCreate(
                ['shop_id' => $shopId, 'line_item_type' => $row['line_item_type']],
                ['default_markup_percent' => $row['default_markup_percent']],
            );
        }

        return redirect()->route('markup-defaults.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Equipment/Index', [
            'equipment' => Equipment::query()
                ->orderBy('name')
                ->get(['id', 'name', 'billing_method', 'rate'])
                ->map(fn (Equipment $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'billing_method' => $item->billing_method->value,
                    'rate' => (float) $item->rate,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Equipment/Create');
    }

    public function store(StoreEquipmentRequest $request): RedirectResponse
    {
        Equipment::create($request->validated());

        return redirect()->route('equipment.index');
    }

    public function edit(Equipment $equipment): Response
    {
        return Inertia::render('Equipment/Edit', [
            'equipment' => [
                'id' => $equipment->id,
                'name' => $equipment->name,
                'billing_method' => $equipment->billing_method->value,
                'rate' => (float) $equipment->rate,
                'notes' => $equipment->notes,
            ],
        ]);
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $equipment->update($request->validated());

        return redirect()->route('equipment.index');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        $equipment->delete();

        return redirect()->route('equipment.index');
    }
}

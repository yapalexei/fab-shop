<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreProcedureRequest;
use App\Http\Requests\UpdateProcedureRequest;
use App\Models\Procedure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class ProcedureController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Procedures;
    }

    public function index(): Response
    {
        return Inertia::render('Procedures/Index', [
            'procedures' => Procedure::query()
                ->withCount(['certifications', 'qualificationRecords'])
                ->orderBy('name')
                ->get()
                ->map(fn (Procedure $procedure) => [
                    'id' => $procedure->id,
                    'name' => $procedure->name,
                    'code' => $procedure->code,
                    'certifications_count' => $procedure->certifications_count,
                    'records_count' => $procedure->qualification_records_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Procedures/Create');
    }

    public function store(StoreProcedureRequest $request): RedirectResponse
    {
        Procedure::create($request->validated());

        return redirect()->route('procedures.index');
    }

    public function edit(Procedure $procedure): Response
    {
        return Inertia::render('Procedures/Edit', [
            'procedure' => $procedure->only(['id', 'name', 'code', 'notes']),
        ]);
    }

    public function update(UpdateProcedureRequest $request, Procedure $procedure): RedirectResponse
    {
        $procedure->update($request->validated());

        return redirect()->route('procedures.index');
    }

    public function destroy(Procedure $procedure): RedirectResponse
    {
        $certifications = $procedure->certifications()->count();
        $records = $procedure->qualificationRecords()->count();

        if ($certifications + $records > 0) {
            return back()->with('error', "{$procedure->name} is still used by {$certifications} certification(s) and {$records} job record(s) - change those first.");
        }

        $procedure->delete();

        return redirect()->route('procedures.index')->with('status', "Deleted {$procedure->name}.");
    }
}

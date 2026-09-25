<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreCertificationRequest;
use App\Http\Requests\UpdateCertificationRequest;
use App\Models\Certification;
use App\Models\Procedure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CertificationController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Certifications;
    }

    public function index(): Response
    {
        return Inertia::render('Certifications/Index', [
            'certifications' => Certification::query()
                ->with(['user:id,name', 'procedure:id,name'])
                ->orderBy('expires_at')
                ->get()
                ->map(fn (Certification $certification) => [
                    'id' => $certification->id,
                    'user' => $certification->user->name,
                    'procedure' => $certification->procedure?->name,
                    'cert_number' => $certification->cert_number,
                    'issuing_body' => $certification->issuing_body,
                    'issued_at' => $certification->issued_at?->toDateString(),
                    'expires_at' => $certification->expires_at?->toDateString(),
                    'is_expired' => $certification->isExpired(),
                ]),
            ...$this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Certifications/Create', $this->options());
    }

    public function store(StoreCertificationRequest $request): RedirectResponse
    {
        Certification::create($request->validated());

        return redirect()->route('certifications.index');
    }

    public function edit(Certification $certification): Response
    {
        return Inertia::render('Certifications/Edit', [
            'certification' => [
                'id' => $certification->id,
                'user_id' => $certification->user_id,
                'procedure_id' => $certification->procedure_id,
                'cert_number' => $certification->cert_number,
                'issuing_body' => $certification->issuing_body,
                'issued_at' => $certification->issued_at?->toDateString(),
                'expires_at' => $certification->expires_at?->toDateString(),
                'notes' => $certification->notes,
            ],
            ...$this->options(),
        ]);
    }

    public function update(UpdateCertificationRequest $request, Certification $certification): RedirectResponse
    {
        $certification->update($request->validated());

        return redirect()->route('certifications.index');
    }

    public function destroy(Certification $certification): RedirectResponse
    {
        $certification->delete();

        return redirect()->route('certifications.index')->with('status', 'Certification deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'members' => Auth::user()->currentShop->members()->orderBy('name')->get(['users.id', 'users.name']),
            'procedures' => Procedure::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}

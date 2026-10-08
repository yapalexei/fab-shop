<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::Customers;
    }

    public function index(): Response
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->with('media')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone'])
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'logo_url' => $customer->logoUrl(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->safe()->except(['logo', 'remove_logo']));

        Images::sync($customer, $request, 'logo', 'logo');

        return redirect()->route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => [...$customer->toArray(), 'logo_url' => $customer->logoUrl()],
            'businessPartnerId' => $customer->businessPartner?->id,
        ]);
    }

    /**
     * A person confirms the details the AI connection set (name, address,
     * email) are right - they print on quotes and decide where one is mailed.
     */
    public function markAiReviewed(Customer $customer): RedirectResponse
    {
        $customer->markReviewed();

        return back()->with('status', 'Marked as reviewed.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->safe()->except(['logo', 'remove_logo']));

        Images::sync($customer, $request, 'logo', 'logo');

        return redirect()->route('customers.index');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        // Quotes and jobs keep a hard reference to their customer (the
        // database refuses the delete) - say why, rather than a 500.
        if (Quote::where('customer_id', $customer->id)->exists() || Job::where('customer_id', $customer->id)->exists()) {
            return back()->with('error', "{$customer->name} has quotes or jobs, so they can't be deleted - their history would be lost.");
        }

        $customer->delete();

        return redirect()->route('customers.index');
    }
}

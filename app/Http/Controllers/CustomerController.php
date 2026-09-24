<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
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
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return redirect()->route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

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

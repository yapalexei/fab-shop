<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create-customer')]
#[Description('Create a new customer. Check search-customers first - if a customer with the same name already exists this refuses, unless allow_duplicate is true. Returns the new customer\'s id.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateCustomerTool extends McpTool
{
    protected function permission(): string
    {
        return 'customers.create';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The customer\'s or company\'s name.')->required(),
            'email' => $schema->string()->description('Email address.'),
            'phone' => $schema->string()->description('Phone number.'),
            'address' => $schema->string()->description('Mailing or job-site address.'),
            'notes' => $schema->string()->description('Internal notes about the customer (not shown to them).'),
            'allow_duplicate' => $schema->boolean()->description('Create even if a customer with this name exists (default false).')->default(false),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allow_duplicate' => ['nullable', 'boolean'],
        ]);

        $existing = Customer::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first();

        if ($existing && ! ($data['allow_duplicate'] ?? false)) {
            return [
                'created' => false,
                'reason' => "A customer named \"{$existing->name}\" already exists (id {$existing->id}). Use update-customer to change it, or pass allow_duplicate: true to create another.",
                'existing_customer_id' => $existing->id,
            ];
        }

        $customer = Customer::create(collect($data)->except('allow_duplicate')->all());

        $this->audit($customer, $agent, "Created customer {$customer->name}");

        return ['created' => true, 'customer' => $this->present($customer)];
    }

    /** @return array<string, mixed> */
    private function present(Customer $customer): array
    {
        return $customer->only(['id', 'name', 'email', 'phone', 'address', 'notes']);
    }
}

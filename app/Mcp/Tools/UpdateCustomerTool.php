<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('update-customer')]
#[Description('Change a customer\'s details. Send only the fields you want to change; send null to clear an optional field. Changing a customer\'s email changes where their quotes are sent, so only do it when you are sure.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
class UpdateCustomerTool extends McpTool
{
    private const FIELDS = ['name', 'email', 'phone', 'address', 'notes'];

    protected function permission(): string
    {
        return 'customers.update';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The customer id (from search-customers).')->required(),
            'name' => $schema->string(),
            'email' => $schema->string(),
            'phone' => $schema->string(),
            'address' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $changes = collect($data)->only(self::FIELDS);

        if ($changes->isEmpty()) {
            throw ValidationException::withMessages(['id' => 'Send at least one field to change.']);
        }

        $customer = Customer::query()->find($data['id']);

        if (! $customer) {
            return ['updated' => false, 'reason' => 'No customer with that id in this shop.'];
        }

        $customer->fill($changes->all());
        $changed = array_keys($customer->getDirty());

        if ($changed === []) {
            return ['updated' => false, 'reason' => 'Nothing to change - those values are already set.'];
        }

        $customer->save();

        // The fields that print on a quote or decide where it is emailed need a
        // person's eyes before anything goes out; notes stay internal.
        if (array_intersect($changed, ['name', 'email', 'phone', 'address'])) {
            $customer->markTouchedByAi();
        }

        $this->audit($customer, $agent, "Updated customer {$customer->name} (".implode(', ', $changed).')');

        return ['updated' => true, 'changed' => $changed, 'customer' => $customer->only(['id', ...self::FIELDS])];
    }
}

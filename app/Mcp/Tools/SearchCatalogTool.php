<?php

namespace App\Mcp\Tools;

use App\Models\BusinessPartner;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-catalog')]
#[Description('Look up the shop\'s catalog: materials, services, equipment, tools, business partners (suppliers) and material types. Returns ids you need for quotes and for creating or updating catalog records. Which types you can search depends on your access (see get-access).')]
#[IsReadOnly]
#[IsIdempotent]
class SearchCatalogTool extends McpTool
{
    /** Search type => the permissions that allow it (any one of them). */
    private const TYPES = [
        'material' => ['materials.view'],
        'service' => ['services.view'],
        'equipment' => ['equipment.view'],
        'tool' => ['tooling.view'],
        'business-partner' => ['business-partners.view'],
        // Only ever an id lookup, needed to create a material or a tool.
        'material-type' => ['materials.view', 'tooling.view'],
    ];

    protected function permission(): ?string
    {
        return null;
    }

    public function shouldRegister(Request $request): bool
    {
        return collect(self::TYPES)->flatten()->unique()->contains(fn (string $permission) => $request->user()?->hasShopPermission($permission));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_keys(self::TYPES))->description('What to search.')->required(),
            'query' => $schema->string()->description('Part of a name. Leave empty to list alphabetically.'),
            'limit' => $schema->integer()->description('How many to return, 1-50 (default 25).')->default(25),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (! collect(self::TYPES[$data['type']])->contains(fn (string $permission) => $agent->hasShopPermission($permission))) {
            return ['results' => [], 'reason' => "This connection has not been given access to {$data['type']} records. Call get-access to see what it can do."];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) ($data['query'] ?? ''))).'%';
        $prices = $agent->hasShopPermission('business-partners.view');

        $rows = match ($data['type']) {
            'material' => $this->search(Material::query()->with('materialType:id,name', 'businessPartners:id,name'), $like, $data, fn (Material $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'unit' => $m->unit,
                'material_type' => $m->materialType?->name,
                ...($prices ? ['suppliers' => $m->businessPartners->map(fn ($p) => ['business_partner_id' => $p->id, 'name' => $p->name, 'dimension' => $p->pivot->dimension, 'price' => (float) $p->pivot->price])->all()] : []),
            ]),
            'service' => $this->search(Service::query()->with('businessPartners:id,name'), $like, $data, fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                ...($prices ? ['suppliers' => $s->businessPartners->map(fn ($p) => ['business_partner_id' => $p->id, 'name' => $p->name, 'price' => (float) $p->pivot->price])->all()] : []),
            ]),
            'equipment' => $this->search(Equipment::query(), $like, $data, fn (Equipment $e) => [
                'id' => $e->id, 'name' => $e->name, 'billing_method' => $e->billing_method->value, 'rate' => (float) $e->rate,
            ]),
            'tool' => $this->search(Tool::query()->with('materialTypes:id,name'), $like, $data, fn (Tool $t) => [
                'id' => $t->id, 'name' => $t->name, 'cost' => (float) $t->cost, 'unit' => $t->unit, 'cuts' => $t->materialTypes->pluck('name')->all(),
            ]),
            'business-partner' => $this->search(BusinessPartner::query(), $like, $data, fn (BusinessPartner $p) => [
                'id' => $p->id, 'name' => $p->name, 'email' => $p->email, 'phone' => $p->phone,
            ]),
            'material-type' => $this->search(MaterialType::query(), $like, $data, fn (MaterialType $t) => ['id' => $t->id, 'name' => $t->name]),
        };

        return ['type' => $data['type'], 'results' => $rows];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function search($query, string $like, array $data, callable $present): array
    {
        return $query
            ->when(trim((string) ($data['query'] ?? '')) !== '', fn ($q) => $q->where('name', 'ilike', $like))
            ->orderBy('name')
            ->limit($data['limit'] ?? 25)
            ->get()
            ->map($present)
            ->values()
            ->all();
    }
}

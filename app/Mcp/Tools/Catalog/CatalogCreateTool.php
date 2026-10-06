<?php

namespace App\Mcp\Tools\Catalog;

use App\Mcp\Tools\McpTool;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;

/**
 * Shape of every "create a catalog record" tool: validate with the same rules
 * as the matching web form, refuse a same-named record unless the AI says it
 * means it, save, and leave an audit entry. A concrete tool only describes
 * its own fields.
 */
abstract class CatalogCreateTool extends McpTool
{
    /** The resource permission prefix, e.g. "materials". */
    abstract protected function resource(): string;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** What one record is called in messages, e.g. "material". */
    abstract protected function noun(): string;

    /** @return array<string, Type> */
    abstract protected function fields(JsonSchema $schema): array;

    /** @return array<string, mixed> Validation rules - same as the web form's. */
    abstract protected function rules(Shop $shop): array;

    /** Attribute names saved straight onto the model. @return array<int, string> */
    abstract protected function attributes(): array;

    /** @return array<string, mixed> */
    abstract protected function present(Model $record): array;

    /** Related data saved after the record exists (pivots). @param array<string, mixed> $data */
    protected function afterCreate(Model $record, array $data): void {}

    protected function permission(): string
    {
        return $this->resource().'.create';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->fields($schema),
            'allow_duplicate' => $schema->boolean()->description("Create even if a {$this->noun()} with this name already exists (default false).")->default(false),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([...$this->rules($shop), 'allow_duplicate' => ['nullable', 'boolean']]);
        $modelClass = $this->model();

        $existing = $modelClass::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first();

        if ($existing && ! ($data['allow_duplicate'] ?? false)) {
            return [
                'created' => false,
                'reason' => "A {$this->noun()} named \"{$existing->name}\" already exists (id {$existing->id}). Update it instead, or pass allow_duplicate: true to create another.",
                'existing_id' => $existing->id,
            ];
        }

        $record = $modelClass::create(collect($data)->only($this->attributes())->all());
        $this->afterCreate($record, $data);

        $this->audit($record, $agent, "Created {$this->noun()} {$record->name}");

        return ['created' => true, Str::snake($this->noun()) => $this->present($record->refresh())];
    }
}

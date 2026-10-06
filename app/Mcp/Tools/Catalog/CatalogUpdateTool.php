<?php

namespace App\Mcp\Tools\Catalog;

use App\Mcp\Tools\McpTool;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * Shape of every "change a catalog record" tool: only the fields sent are
 * changed (null clears an optional one), validated with the web form's rules,
 * and an audit entry says what changed.
 */
abstract class CatalogUpdateTool extends McpTool
{
    abstract protected function resource(): string;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function noun(): string;

    /** @return array<string, Type> */
    abstract protected function fields(JsonSchema $schema): array;

    /** @return array<string, mixed> The create rules; "required" is relaxed for updates. */
    abstract protected function rules(Shop $shop): array;

    /** @return array<int, string> */
    abstract protected function attributes(): array;

    /** @return array<string, mixed> */
    abstract protected function present(Model $record): array;

    /** Relations replaced when their key is sent. @param array<string, mixed> $data @param array<int, string> $sent @return array<int, string> */
    protected function applyRelations(Model $record, array $data, array $sent): array
    {
        return [];
    }

    protected function permission(): string
    {
        return $this->resource().'.update';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description("The {$this->noun()} id (from search-catalog).")->required(),
            ...$this->fields($schema),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $sent = array_keys($request->all());
        // Top-level fields become optional (only what's sent is changed);
        // the rows inside a list keep their own requirements.
        $rules = collect($this->rules($shop))->map(function ($rule, string $key) {
            if (str_contains($key, '.')) {
                return $rule;
            }

            return ['sometimes', ...array_values(array_filter($rule, fn ($part) => $part !== 'required'))];
        })->all();

        $data = $request->validate(['id' => ['required', 'integer'], ...$rules]);

        $fieldNames = collect(array_keys($this->rules($shop)))->filter(fn (string $key) => ! str_contains($key, '.'))->all();

        if (array_intersect($sent, $fieldNames) === []) {
            throw ValidationException::withMessages(['id' => 'Send at least one field to change.']);
        }

        $modelClass = $this->model();
        $record = $modelClass::query()->find($data['id']);

        if (! $record) {
            return ['updated' => false, 'reason' => "No {$this->noun()} with that id in this shop."];
        }

        $record->fill(collect($data)->only($this->attributes())->all());
        $changed = array_keys($record->getDirty());
        $record->save();

        $changed = [...$changed, ...$this->applyRelations($record, $data, $sent)];

        if ($changed === []) {
            return ['updated' => false, 'reason' => 'Nothing to change - those values are already set.'];
        }

        $this->audit($record, $agent, "Updated {$this->noun()} {$record->name} (".implode(', ', $changed).')');

        return ['updated' => true, 'changed' => $changed, Str::snake($this->noun()) => $this->present($record->refresh())];
    }
}

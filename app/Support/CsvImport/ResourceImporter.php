<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;

/**
 * One resource's CSV shape: what its template looks like, how a raw row
 * becomes model attributes (resolving any name-based relational columns
 * along the way), and the validation those attributes must pass. Every
 * resource is matched for "already exists" by a case-insensitive `name`
 * column - the only field an import row reliably carries.
 */
abstract class ResourceImporter
{
    /** The route/registry key, e.g. 'materials'. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return class-string<Model> */
    abstract public function modelClass(): string;

    abstract public function feature(): Feature;

    /** @return array<int, string> the template's header row, in order */
    abstract public function templateColumns(): array;

    /**
     * Template columns that must be mapped to a file column (the row can't
     * be valid without them). Everything else may be left unmapped, which
     * reads as blank. Defaults to just the name every row is matched on.
     *
     * @return array<int, string>
     */
    public function requiredColumns(): array
    {
        return ['name'];
    }

    /**
     * Turn one raw CSV row (trimmed strings, keyed by template column name)
     * into model attributes for $shop, resolving any name-based relational
     * column to an id. An unresolvable reference is reported in `errors`
     * rather than silently dropped or auto-created.
     *
     * @param  array<string, string>  $row
     * @return array{attributes: array<string, mixed>, relations: array<string, mixed>, errors: array<int, string>}
     */
    abstract public function resolveRow(array $row, Shop $shop): array;

    /**
     * Validation rules for the *resolved* attributes (post name-lookup) -
     * mirrors that resource's own Store{X}Request rules.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Attach any many-to-many relations resolveRow() resolved, once the
     * model is saved. No-op unless overridden.
     *
     * @param  array<string, mixed>  $relations
     */
    public function applyRelations(Model $model, array $relations): void
    {
        //
    }

    /**
     * A last check before creating a genuinely *new* record (never called
     * for an update of an existing or already-imported-this-run one) -
     * MaterialTypeImporter uses this for the per-shop custom-type cap, so
     * an import can't be used to bypass it. Return an error message to
     * block the row, or null to allow it.
     */
    public function beforeCreate(Shop $shop): ?string
    {
        return null;
    }
}

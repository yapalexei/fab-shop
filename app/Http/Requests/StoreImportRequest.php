<?php

namespace App\Http\Requests;

use App\Support\CsvImport\ImporterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-shop');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'overwrite' => ['nullable', 'boolean'],
            // Sent once the owner has matched their columns to ours:
            // template column => zero-based file column ('' = leave blank).
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            // Only a supplier price list needs one: whose prices these are.
            'business_partner_id' => [
                Rule::requiredIf(fn () => $this->route('resource') === 'supplier-prices' && $this->routeIs('imports.store')),
                'nullable',
                Rule::exists('business_partners', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
        ];
    }

    /**
     * A mapping must fill every required column and use each file column once.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! is_array($this->input('mapping'))) {
                return;
            }

            $importer = ImporterRegistry::for((string) $this->route('resource'));
            $mapping = $this->input('mapping');

            foreach ($importer->requiredColumns() as $column) {
                if (($mapping[$column] ?? null) === null) {
                    $validator->errors()->add("mapping.{$column}", "Choose which column holds \"{$column}\".");
                }
            }

            $used = array_filter(array_intersect_key($mapping, array_flip($importer->templateColumns())), fn ($i) => $i !== null);

            if (count($used) !== count(array_unique($used))) {
                $validator->errors()->add('mapping', 'Each column in your file can only be used once.');
            }
        }];
    }
}

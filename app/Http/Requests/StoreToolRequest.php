<?php

namespace App\Http\Requests;

use App\Support\Images;
use App\Support\SupplierPrices;
use App\Support\ToolModels;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // At least one - a tool with no rated material defeats the point
            // of telling a steel bit from an aluminum one.
            'cuts' => ['required', 'array', 'min:1'],
            'cuts.*' => [
                'distinct',
                Rule::exists('material_types', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'cost' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'part_number' => SupplierPrices::LINK_RULES['part_number'],
            'source_url' => SupplierPrices::LINK_RULES['source_url'],
            'mark_price_checked' => ['nullable', 'boolean'],
            'image' => Images::rules(),
            'remove_image' => ['nullable', 'boolean'],
            'model' => ToolModels::rules(),
            'remove_model' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_url.url' => 'The link must be a full web address starting with http:// or https://.',
            'cuts.required' => 'Pick at least one material this tool can cut.',
            'cuts.min' => 'Pick at least one material this tool can cut.',
            'model.extensions' => 'The 3D file must be STEP, STL, 3MF, OBJ, DXF or DWG.',
        ];
    }
}

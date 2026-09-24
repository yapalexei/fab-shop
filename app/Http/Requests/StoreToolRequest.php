<?php

namespace App\Http\Requests;

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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuts.required' => 'Pick at least one material this tool can cut.',
            'cuts.min' => 'Pick at least one material this tool can cut.',
        ];
    }
}

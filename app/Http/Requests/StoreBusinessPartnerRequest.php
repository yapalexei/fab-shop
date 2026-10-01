<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBusinessPartnerRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'materials' => ['nullable', 'array'],
            'materials.*.material_id' => [
                'required',
                Rule::exists('materials', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'materials.*.dimension' => ['nullable', 'string', 'max:100'],
            'materials.*.price' => ['required', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => [
                'required',
                Rule::exists('services', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'services.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * The same material can be listed more than once for a supplier, but
     * only at different sizes (blank counts as a size of its own).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $seen = [];

            foreach ((array) $this->input('materials', []) as $index => $row) {
                $key = ($row['material_id'] ?? '').'|'.mb_strtolower(trim((string) ($row['dimension'] ?? '')));

                if (isset($seen[$key])) {
                    $validator->errors()->add("materials.{$index}.dimension", 'This material is already listed at this size - give it a different size or remove the duplicate.');
                }

                $seen[$key] = true;
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'materials.*.material_id' => 'material',
            'materials.*.dimension' => 'size',
            'materials.*.price' => 'price',
            'services.*.service_id' => 'service',
            'services.*.price' => 'price',
        ];
    }
}

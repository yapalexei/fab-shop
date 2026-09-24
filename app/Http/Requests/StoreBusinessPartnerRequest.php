<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'materials.*.material_id' => 'material',
            'materials.*.price' => 'price',
            'services.*.service_id' => 'service',
            'services.*.price' => 'price',
        ];
    }
}

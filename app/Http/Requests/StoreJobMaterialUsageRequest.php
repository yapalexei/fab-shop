<?php

namespace App\Http\Requests;

use App\Enums\MaterialUsageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobMaterialUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'material_id' => [
                'nullable',
                Rule::exists('materials', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'description' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::enum(MaterialUsageType::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

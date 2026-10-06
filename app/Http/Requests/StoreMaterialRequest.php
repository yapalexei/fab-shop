<?php

namespace App\Http\Requests;

use App\Support\Images;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'image' => Images::rules(),
            'remove_image' => ['nullable', 'boolean'],
            'material_type_id' => [
                'nullable',
                Rule::exists('material_types', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
        ];
    }
}

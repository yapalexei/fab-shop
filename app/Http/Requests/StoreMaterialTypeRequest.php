<?php

namespace App\Http\Requests;

use App\Models\MaterialType;
use App\Support\MaterialTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMaterialTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * Case-insensitive uniqueness within the shop (MaterialType's global
     * scope keeps the check to the current shop), plus the custom-type cap.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $taken = MaterialType::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($this->input('name')))])
                ->when($this->route('material_type'), fn ($query, MaterialType $current) => $query->whereKeyNot($current->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This shop already has a material type with that name.');
            }

            if ($this->isMethod('post') && MaterialType::query()->where('is_default', false)->count() >= MaterialTypes::customLimit()) {
                $validator->errors()->add('name', 'This shop already has the maximum of '.MaterialTypes::customLimit().' custom material types.');
            }
        }];
    }
}

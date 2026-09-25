<?php

namespace App\Http\Requests;

use App\Models\Procedure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProcedureRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Case-insensitive uniqueness within the shop, same pattern as
     * MaterialType (Procedure's global scope keeps the check to the shop).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $taken = Procedure::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($this->input('name')))])
                ->when($this->route('procedure'), fn ($query, Procedure $current) => $query->whereKeyNot($current->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This shop already has a procedure with that name.');
            }
        }];
    }
}

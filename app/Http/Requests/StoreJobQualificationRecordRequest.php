<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobQualificationRecordRequest extends FormRequest
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
            'procedure_id' => [
                'nullable',
                Rule::exists('procedures', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'performed_by' => [
                'required',
                Rule::exists('shop_user', 'user_id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'description' => ['required', 'string', 'max:255'],
            'performed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

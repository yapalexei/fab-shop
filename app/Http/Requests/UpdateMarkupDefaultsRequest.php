<?php

namespace App\Http\Requests;

use App\Enums\LineItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMarkupDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-shop');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'defaults' => ['required', 'array'],
            'defaults.*.line_item_type' => ['required', Rule::enum(LineItemType::class)],
            'defaults.*.default_markup_percent' => ['required', 'numeric', 'min:0'],
        ];
    }
}

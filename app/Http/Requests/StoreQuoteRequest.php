<?php

namespace App\Http\Requests;

use App\Enums\LineItemType;
use App\Enums\QuoteStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
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
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->where('shop_id', $this->user()->current_shop_id),
            ],
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.type' => ['required', Rule::enum(LineItemType::class)],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'line_items.*.markup_percent' => ['required', 'numeric', 'min:0'],
            'line_items.*.override_total' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

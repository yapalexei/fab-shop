<?php

namespace App\Http\Requests;

use App\Enums\LineItemType;
use App\Support\Attachments;
use Closure;
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
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['file', 'max:'.Attachments::MAX_KILOBYTES],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.type' => ['required', Rule::enum(LineItemType::class)],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'line_items.*.markup_percent' => ['required', 'numeric', 'min:0'],
            'line_items.*.override_total' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.material_id' => [
                'nullable',
                Rule::exists('materials', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'line_items.*.service_id' => [
                'nullable',
                Rule::exists('services', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'line_items.*.business_partner_id' => [
                'nullable',
                Rule::exists('business_partners', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'line_items.*.material_dimension' => ['nullable', 'string', 'max:100'],
            'line_items.*.user_id' => [
                'nullable',
                Rule::exists('shop_user', 'user_id')->where('shop_id', $this->user()->current_shop_id),
                // A labor line is either an internal employee or an outsourced
                // service - never both. $attribute resolves to the concrete
                // "line_items.{index}.user_id" for this row.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $index = explode('.', $attribute)[1];

                    if (filled($this->input("line_items.{$index}.service_id"))) {
                        $fail('A line item cannot have both an employee and an outsourced service.');
                    }
                },
            ],
            'line_items.*.equipment_id' => [
                'nullable',
                Rule::exists('equipment', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
            'line_items.*.tool_id' => [
                'nullable',
                Rule::exists('tools', 'id')->where('shop_id', $this->user()->current_shop_id),
            ],
        ];
    }

    /**
     * Friendly names so errors read "The description field is required"
     * instead of "The line_items.0.description field is required" - the
     * row-level UI already places the message under the right line item.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'due_date' => 'due date',
            'line_items' => 'line items',
            'line_items.*.type' => 'type',
            'line_items.*.description' => 'description',
            'line_items.*.quantity' => 'quantity',
            'line_items.*.unit_cost' => 'unit cost',
            'line_items.*.markup_percent' => 'markup percent',
            'line_items.*.override_total' => 'override total',
            'line_items.*.material_id' => 'material',
            'line_items.*.service_id' => 'service',
            'line_items.*.business_partner_id' => 'business partner',
            'line_items.*.user_id' => 'employee',
            'line_items.*.equipment_id' => 'equipment',
            'line_items.*.tool_id' => 'tool',
        ];
    }
}

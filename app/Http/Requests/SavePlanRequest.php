<?php

namespace App\Http\Requests;

use App\Enums\Feature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A platform admin creating a tier or editing one. Money is in cents (the
 * page converts from dollars), seats and the extra-seat price are nullable
 * (null = unlimited / can't be bought), and `expires_on` is the date the tier
 * leaves the public list.
 */
class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('platform-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'monthly_price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'extra_seat_price_cents' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'features' => ['array'],
            'features.*' => [Rule::enum(Feature::class)],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'show_expiry' => ['boolean'],
        ];
    }
}

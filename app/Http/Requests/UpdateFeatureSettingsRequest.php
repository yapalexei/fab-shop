<?php

namespace App\Http\Requests;

use App\Enums\Feature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateFeatureSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array'],
            'settings.*.feature' => ['required', Rule::enum(Feature::class)],
            'settings.*.price_cents' => ['required', 'integer', 'min:0'],
            'settings.*.trial_days' => ['required', 'integer', 'min:0'],
        ];
    }
}

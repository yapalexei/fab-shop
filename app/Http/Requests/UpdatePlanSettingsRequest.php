<?php

namespace App\Http\Requests;

use App\Enums\Feature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdatePlanSettingsRequest extends FormRequest
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
            'plans' => ['required', 'array'],
            'plans.*.id' => ['required', 'exists:plans,id'],
            'plans.*.user_limit' => ['nullable', 'integer', 'min:1'],
            'plans.*.features' => ['array'],
            'plans.*.features.*' => [Rule::enum(Feature::class)],
        ];
    }
}

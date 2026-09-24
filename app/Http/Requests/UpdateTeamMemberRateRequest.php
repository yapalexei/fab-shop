<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTeamMemberRateRequest extends FormRequest
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
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateShopSeatLimitRequest extends FormRequest
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
            'seat_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

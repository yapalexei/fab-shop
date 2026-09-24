<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('shop-settings.update');
    }

    /**
     * Blank numeric/timezone fields are stored as null, which falls back to
     * config('shop.defaults.*') - so a shop can go back to the default.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'],
            'stale_quote_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'win_rate_window_days' => ['nullable', 'integer', 'min:7', 'max:730'],
            'job_board_drag_enabled' => ['nullable', 'boolean'],
        ];
    }
}

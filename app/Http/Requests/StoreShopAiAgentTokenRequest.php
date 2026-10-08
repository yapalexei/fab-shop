<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreShopAiAgentTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-shop');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // What the owner will recognise it by in the list ("Claude Desktop").
            'name' => ['required', 'string', 'max:60'],
            // Blank = the default lifetime; there is deliberately no "never".
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            // Read-only unless this is ticked - see McpTool::WRITE_ABILITY.
            'allow_changes' => ['nullable', 'boolean'],
        ];
    }
}

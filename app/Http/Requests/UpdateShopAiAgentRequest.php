<?php

namespace App\Http\Requests;

use App\Support\AgentAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateShopAiAgentRequest extends FormRequest
{
    /**
     * Owner-only, never delegable: what a connected AI can see - and now
     * change - is the shop's data leaving the shop, and its records being
     * edited by software.
     */
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
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(AgentAccess::allPermissions())],
        ];
    }
}

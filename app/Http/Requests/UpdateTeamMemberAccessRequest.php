<?php

namespace App\Http\Requests;

use App\Enums\ShopPermission;
use App\Http\Controllers\RolePermissionController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTeamMemberAccessRequest extends FormRequest
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
        $permissions = array_column(ShopPermission::cases(), 'value');

        return [
            // Owner isn't assignable - a shop has exactly one owner.
            'role' => ['required', Rule::in(RolePermissionController::EDITABLE_ROLES)],
            'overrides' => ['present', 'array:'.implode(',', $permissions)],
            'overrides.*' => ['nullable', 'boolean'],
        ];
    }
}

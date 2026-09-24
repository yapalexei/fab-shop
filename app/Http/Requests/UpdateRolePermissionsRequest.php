<?php

namespace App\Http\Requests;

use App\Support\ShopPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
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
        $rules = ['grants' => ['present', 'array:'.implode(',', ShopPermissions::EDITABLE_ROLES)]];

        foreach (ShopPermissions::EDITABLE_ROLES as $role) {
            $rules["grants.{$role}"] = ['sometimes', 'array'];
            $rules["grants.{$role}.*"] = ['distinct', Rule::in(ShopPermissions::all())];
        }

        return $rules;
    }
}

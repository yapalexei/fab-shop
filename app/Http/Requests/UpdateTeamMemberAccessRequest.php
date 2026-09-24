<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamMemberAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('team.manage-access');
    }

    /**
     * Overrides arrive as a list of {permission, value} rather than a map
     * keyed by permission name - the names contain dots, which the
     * validator would read as nesting.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Owner isn't assignable - a shop has exactly one owner.
            'role' => ['required', Rule::in(ShopPermissions::EDITABLE_ROLES)],
            'overrides' => ['present', 'array'],
            'overrides.*.permission' => ['required', 'distinct', Rule::in(ShopPermissions::all())],
            'overrides.*.value' => ['present', 'nullable', 'boolean'],
        ];
    }

    /**
     * Non-owners can't escalate: no role changes, and no *new* allow for a
     * permission they don't hold themselves. (An allow the owner already set
     * can be left as-is, so saving the form doesn't fail on it.)
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (Gate::allows('manage-shop') || $validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var User $member */
            $member = $this->route('member');

            if ($this->input('role') !== $member->getRoleNames()->first()) {
                $validator->errors()->add('role', "Only the shop owner can change someone's role.");
            }

            $existing = $this->user()->currentShop->members()->whereKey($member->id)->first()?->pivot->permission_overrides ?? [];
            $own = $this->user()->shopPermissions();

            foreach ($this->input('overrides', []) as $index => $row) {
                $allowing = filter_var($row['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
                $alreadyAllowed = ($existing[$row['permission']] ?? null) === true;

                if ($allowing && ! $alreadyAllowed && ! in_array($row['permission'], $own, true)) {
                    $validator->errors()->add("overrides.{$index}.value", "You can't give access you don't have yourself ({$row['permission']}).");
                }
            }
        }];
    }
}

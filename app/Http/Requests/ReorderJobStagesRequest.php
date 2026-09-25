<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReorderJobStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // A plain 'exists:job_stages,id' rule bypasses JobStage's
        // BelongsToShop global scope entirely (validation rules query the
        // table directly, not through the model) - scoped explicitly here
        // so another shop's stage id can't even be named in the payload,
        // rather than relying on the controller's own scoped query to
        // silently drop it.
        return [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => [
                'integer',
                Rule::exists('job_stages', 'id')->where('shop_id', Auth::user()->current_shop_id),
            ],
        ];
    }
}

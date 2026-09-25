<?php

namespace App\Http\Requests;

use App\Models\JobStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJobStageRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * Case-insensitive uniqueness within the shop (JobStage's global scope
     * keeps the check to the current shop).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $taken = JobStage::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($this->input('name')))])
                ->when($this->route('job_stage'), fn ($query, JobStage $current) => $query->whereKeyNot($current->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'This shop already has a stage with that name.');
            }
        }];
    }
}

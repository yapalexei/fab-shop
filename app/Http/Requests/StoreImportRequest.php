<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'overwrite' => ['nullable', 'boolean'],
        ];
    }
}

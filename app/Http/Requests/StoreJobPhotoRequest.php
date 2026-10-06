<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1'],
            // Raster formats only (no SVG - it can carry script - and the
            // thumbnail step needs a bitmap).
            'photos.*' => ['mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }
}

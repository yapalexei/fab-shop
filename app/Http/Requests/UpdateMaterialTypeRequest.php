<?php

namespace App\Http\Requests;

class UpdateMaterialTypeRequest extends StoreMaterialTypeRequest
{
    /**
     * Built-in types are shared vocabulary across the app - only a shop's
     * own custom types can be renamed.
     */
    public function authorize(): bool
    {
        return ! $this->route('material_type')->is_default;
    }
}

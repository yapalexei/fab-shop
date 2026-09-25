<?php

namespace App\Http\Requests;

class UpdateJobStageRequest extends StoreJobStageRequest
{
    /**
     * Not Started/Shipped/Invoiced are the fixed ends of every shop's
     * pipeline - only a shop's own custom stages can be renamed.
     */
    public function authorize(): bool
    {
        return ! $this->route('job_stage')->isBuiltIn();
    }
}

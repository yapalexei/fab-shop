<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a job's qualification ledger - who performed a procedure and
 * whether they held a valid certification for it at the time, deliberately
 * separate from Certification itself (see the migration). Only ever grows,
 * same as JobMaterialUsage.
 */
class JobQualificationRecord extends Model
{
    use BelongsToShop;

    protected $fillable = ['job_id', 'procedure_id', 'performed_by', 'description', 'performed_at', 'was_qualified', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'was_qualified' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

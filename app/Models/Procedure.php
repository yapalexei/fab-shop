<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\ProcedureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop-defined process people get qualified against - a welding WPS is
 * one example, but so is an NDT method, a coating line, or equipment that
 * needs an operator card. Deliberately not welding-specific.
 */
class Procedure extends Model
{
    /** @use HasFactory<ProcedureFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'code', 'notes'];

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    public function qualificationRecords(): HasMany
    {
        return $this->hasMany(JobQualificationRecord::class);
    }
}

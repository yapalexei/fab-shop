<?php

namespace App\Models;

use App\Enums\MaterialUsageType;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a job's material-usage ledger - actual material consumed or
 * wasted, as opposed to QuoteLineItem, which is the estimate a job's price
 * is fixed to. See the migration for why this never gets cleaned up when a
 * job's status is reversed.
 */
class JobMaterialUsage extends Model
{
    use BelongsToShop;

    protected $fillable = ['job_id', 'material_id', 'description', 'unit', 'quantity', 'unit_cost', 'type', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'type' => MaterialUsageType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * quantity x unit_cost - what this entry actually cost the shop.
     */
    protected function cost(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->quantity * (float) $this->unit_cost);
    }
}

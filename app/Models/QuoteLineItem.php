<?php

namespace App\Models;

use App\Enums\LineItemType;
use Database\Factories\QuoteLineItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLineItem extends Model
{
    /** @use HasFactory<QuoteLineItemFactory> */
    use HasFactory;

    protected $fillable = [
        'type', 'description', 'quantity', 'unit_cost', 'markup_percent', 'override_total', 'sort_order',
        'material_id', 'service_id', 'business_partner_id', 'user_id', 'equipment_id', 'tool_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => LineItemType::class,
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'override_total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function businessPartner(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /**
     * quantity x unit_cost, before markup - what the shop pays.
     */
    protected function cost(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->quantity * (float) $this->unit_cost);
    }

    /**
     * The computed sell price (cost + markup), ignoring any manual override.
     */
    protected function computedTotal(): Attribute
    {
        return Attribute::get(fn (): float => $this->cost * (1 + (float) $this->markup_percent / 100));
    }

    /**
     * What the customer is actually charged for this line: the manual
     * override when set, otherwise the computed cost + markup.
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => $this->override_total !== null
            ? (float) $this->override_total
            : $this->computed_total);
    }
}

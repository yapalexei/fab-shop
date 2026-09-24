<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['customer_id', 'status', 'notes', 'sent_at'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('sort_order');
    }

    /**
     * Total cost (quantity x unit_cost) across all lines, before markup.
     */
    protected function cost(): Attribute
    {
        return Attribute::get(fn (): float => $this->lineItems->sum->cost);
    }

    /**
     * What the customer is actually charged - sum of each line's total
     * (its manual override if set, otherwise cost + markup).
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => $this->lineItems->sum->total);
    }

    protected function margin(): Attribute
    {
        return Attribute::get(fn (): float => $this->total - $this->cost);
    }

    protected function marginPercent(): Attribute
    {
        return Attribute::get(fn (): float => $this->total > 0 ? ($this->margin / $this->total) * 100 : 0.0);
    }
}

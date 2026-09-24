<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use BelongsToShop, HasFactory;

    protected $table = 'fabrication_jobs';

    protected $fillable = ['quote_id', 'customer_id', 'job_number', 'status'];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * A job's price is whatever its quote was won at - never re-derived
     * independently, so job cost/pricing can't drift from the quote it
     * came from.
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => $this->quote->total);
    }

    /**
     * Generate the next job number for the current shop, e.g. "J-0001".
     * Relies on BelongsToShop's global scope for the shop boundary, same as
     * every other tenant-scoped query - never filter by shop_id by hand.
     */
    public static function nextNumber(): string
    {
        return 'J-'.str_pad((string) (static::count() + 1), 4, '0', STR_PAD_LEFT);
    }
}

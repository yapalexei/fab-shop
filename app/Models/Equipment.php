<?php

namespace App\Models;

use App\Enums\BillingMethod;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use BelongsToShop, HasFactory;

    protected $table = 'equipment';

    protected $fillable = ['name', 'billing_method', 'rate', 'notes'];

    protected function casts(): array
    {
        return [
            'billing_method' => BillingMethod::class,
            'rate' => 'decimal:2',
        ];
    }
}

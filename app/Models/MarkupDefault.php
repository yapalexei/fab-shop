<?php

namespace App\Models;

use App\Enums\LineItemType;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class MarkupDefault extends Model
{
    use BelongsToShop;

    protected $fillable = ['line_item_type', 'default_markup_percent'];

    protected function casts(): array
    {
        return [
            'line_item_type' => LineItemType::class,
            'default_markup_percent' => 'decimal:2',
        ];
    }

    /**
     * The current shop's default markup % for a line-item type, or 0 if
     * none has been set.
     */
    public static function forType(LineItemType $type): float
    {
        $default = static::query()->where('line_item_type', $type->value)->first();

        return $default ? (float) $default->default_markup_percent : 0.0;
    }
}

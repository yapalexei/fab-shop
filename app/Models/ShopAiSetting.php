<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\ShopAiSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A shop's own AI provider, model and key (bring-your-own-key). The key is
 * encrypted at rest and hidden from serialization, so it can't end up in an
 * Inertia prop or a JSON response by accident - the settings page only ever
 * gets keyHint().
 */
class ShopAiSetting extends Model
{
    /** @use HasFactory<ShopAiSettingFactory> */
    use BelongsToShop, HasFactory;

    // last_tested_at is deliberately not fillable: only a successful test
    // call sets it, never a form field.
    protected $fillable = ['source', 'provider', 'model', 'api_key'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'last_tested_at' => 'datetime',
        ];
    }

    public function hasKey(): bool
    {
        return filled($this->api_key);
    }

    /**
     * Just enough of the key to recognise it ("ends in x4f2") - never the key.
     */
    public function keyHint(): ?string
    {
        if (! $this->hasKey()) {
            return null;
        }

        return '…'.substr($this->api_key, -4);
    }
}

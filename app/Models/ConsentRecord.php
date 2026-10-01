<?php

namespace App\Models;

use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One immutable fact: this person agreed to (or withdrew from) this document
 * version, in this wording, at this time, from this IP. Deliberately not
 * BelongsToShop - registration happens before a shop exists, and no tenant
 * should ever read these - and never updated or deleted by the app. To
 * change someone's status, record a new row (see App\Support\Consent).
 */
class ConsentRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'shop_id', 'subject_email', 'subject_phone', 'type', 'granted',
        'document_version', 'consent_text', 'source', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'granted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Consent records are append-only.'));
        static::deleting(fn () => throw new LogicException('Consent records are append-only.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

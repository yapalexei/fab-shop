<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class QuickBooksConnection extends Model
{
    use BelongsToShop;

    protected $table = 'quickbooks_connections';

    protected $fillable = [
        'realm_id', 'access_token', 'refresh_token', 'expires_at', 'refresh_token_expires_at', 'connected_at',
    ];

    protected function casts(): array
    {
        return [
            // Belt-and-suspenders on top of column-level storage as text -
            // these are long-lived OAuth secrets, not session tokens.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

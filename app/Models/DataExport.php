<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

/**
 * One request to export a shop's data as a zip. The file lives on the default
 * disk (web and queue workers don't share a filesystem) and is removed after
 * `expires_at`. status / path / size are set by the job, never from a form.
 */
class DataExport extends Model
{
    use BelongsToShop;

    protected $fillable = ['requested_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'ready' && $this->path !== null && $this->expires_at?->isFuture();
    }
}

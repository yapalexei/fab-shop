<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One CSV upload's outcome - the frontend polls this row (GET
 * /imports/status/{import}) rather than waiting on the upload request,
 * since processing happens in a queued job.
 */
class Import extends Model
{
    use BelongsToShop;

    protected $fillable = [
        'resource', 'status', 'overwrite', 'original_filename', 'imported_by',
        'created_count', 'updated_count', 'skipped_count', 'errors',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'overwrite' => 'boolean',
            'errors' => 'array',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}

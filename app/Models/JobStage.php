<?php

namespace App\Models;

use App\Enums\JobStageRole;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\JobStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One station on a shop's own job board, in shop-defined order. Not Started,
 * Shipped and Invoiced (role set, see JobStageRole) always exist, in that
 * relative position, and can't be renamed/reordered/deleted - everything
 * between them is the shop's own custom pipeline (role null), fully
 * editable. See App\Support\JobStages for seeding and App\Models\Job's
 * canTransitionTo() for how position/role drive the board's state machine.
 */
class JobStage extends Model
{
    /** @use HasFactory<JobStageFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'position', 'role'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'role' => JobStageRole::class,
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function isBuiltIn(): bool
    {
        return $this->role !== null;
    }
}

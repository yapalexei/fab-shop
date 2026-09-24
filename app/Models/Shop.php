<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    protected $fillable = ['name', 'owner_id', 'plan_id', 'trial_ends_at', 'trial_ended_notified_at'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'trial_ended_notified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * True if the feature is in the shop's current plan, or the shop is still
     * on trial (trial shops get every feature during the trial window).
     */
    public function hasFeature(Feature $feature): bool
    {
        return $this->onTrial() || ($this->plan?->hasFeature($feature) ?? false);
    }

    /**
     * @return Collection<int, Feature>
     */
    public function enabledFeatures(): Collection
    {
        if ($this->onTrial()) {
            return collect(Feature::cases());
        }

        return $this->plan?->features() ?? collect();
    }
}

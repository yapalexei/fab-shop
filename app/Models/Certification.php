<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Support\Attachments;
use App\Support\CertificationDocuments;
use Carbon\CarbonInterface;
use Database\Factories\CertificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A person's qualification - optionally tied to one of the shop's own
 * procedures. Not tied to one on purpose: plenty of real certs (OSHA 10, a
 * forklift card) aren't about a specific shop-defined procedure at all.
 */
class Certification extends Model implements HasMedia
{
    /** @use HasFactory<CertificationFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia;

    protected $fillable = ['user_id', 'procedure_id', 'cert_number', 'issuing_body', 'issued_at', 'expires_at', 'notes'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(CertificationDocuments::COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        Attachments::registerThumbnail($this, $media, CertificationDocuments::COLLECTION);
    }

    /**
     * The uploaded scans/photos of the certificate, oldest first.
     *
     * @return Collection<int, Media>
     */
    public function documents(): Collection
    {
        return $this->getMedia(CertificationDocuments::COLLECTION)->sortBy('created_at')->values();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether this certification was in force on the given date - issued by
     * then (or no issue date recorded) and not yet expired. Used to compute
     * JobQualificationRecord::$was_qualified at the moment work is logged.
     */
    public function coversOn(CarbonInterface|string $date): bool
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return ($this->issued_at === null || $this->issued_at->lessThanOrEqualTo($date))
            && ($this->expires_at === null || $this->expires_at->greaterThanOrEqualTo($date));
    }
}

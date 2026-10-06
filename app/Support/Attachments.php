<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files attached to a quote (and carried over to its job): the upload limit,
 * how a file is classified and thumbnailed, the shape sent to the frontend,
 * and how a file is served back.
 */
final class Attachments
{
    public const MAX_KILOBYTES = 20480;

    /** Types a browser can show inline without running anything. */
    private const INLINE_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /** Raster images, which get a generated square thumbnail. */
    private const RASTER_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * What a file is, for choosing a preview. CAD formats have no reliable
     * mime type (a STEP file sniffs as plain text), so extension decides.
     * Add a format here (and to the frontend's icon/viewer map) to support it.
     */
    private const KINDS_BY_EXTENSION = [
        'step' => 'step', 'stp' => 'step',
        'stl' => 'stl',
        '3mf' => '3mf',
        'obj' => 'obj',
        'dxf' => 'dxf',
        'dwg' => 'dwg',
    ];

    public const THUMBNAIL = 'preview';

    /**
     * 'image' | 'pdf' | 'step' | 'stl' | '3mf' | 'obj' | 'dxf' | 'dwg' | 'file'
     */
    public static function kind(Media $file): string
    {
        if (str_starts_with((string) $file->mime_type, 'image/')) {
            return 'image';
        }

        if ($file->mime_type === 'application/pdf') {
            return 'pdf';
        }

        return self::KINDS_BY_EXTENSION[strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION))] ?? 'file';
    }

    /**
     * Square thumbnails for raster uploads, made as the file is stored. SVGs
     * are skipped (they scale on their own and need Imagick to rasterize),
     * as is everything else that isn't a bitmap.
     */
    public static function registerThumbnail(HasMedia $model, ?Media $media, string $collection = 'attachments'): void
    {
        if ($media !== null && ! in_array($media->mime_type, self::RASTER_TYPES, true)) {
            return;
        }

        // Synchronous, like job photos: it should exist when the upload's
        // response reaches the browser.
        $model->addMediaConversion(self::THUMBNAIL)
            ->fit(Fit::Crop, 400, 400)
            ->nonQueued()
            ->performOnCollections($collection);
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return Collection<int, array<string, mixed>>
     */
    public static function props(Collection $media, string $showRoute, Model $owner): Collection
    {
        return $media->map(fn (Media $file) => [
            'id' => $file->id,
            'name' => $file->file_name,
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'kind' => self::kind($file),
            // Full-size file (or download) - always present.
            'file_path' => route($showRoute, [$owner, $file->id]),
            // A square image to show in the list, or null when there isn't
            // one yet (STEP, STL, PDF...) and the client draws a generic icon
            // for `kind`. A future converter service fills this in.
            'thumbnail' => self::thumbnailRoute($file, $showRoute, $owner),
        ])->values();
    }

    private static function thumbnailRoute(Media $file, string $showRoute, Model $owner): ?string
    {
        $base = [$owner, $file->id];

        if ($file->mime_type === 'image/svg+xml') {
            return route($showRoute, $base).'?thumbnail=1';
        }

        return $file->hasGeneratedConversion(self::THUMBNAIL)
            ? route($showRoute, $base).'?thumbnail=1'
            : null;
    }

    /**
     * Streamed through the app (so access follows the owner's permissions and
     * works on any disk). Only safe types are shown inline - anything else,
     * SVG and HTML included, downloads rather than rendering on our origin.
     */
    public static function respond(Request $request, Media $file): StreamedResponse
    {
        $conversion = $request->boolean('thumbnail') && $file->hasGeneratedConversion(self::THUMBNAIL)
            ? self::THUMBNAIL
            : '';

        $isSvg = $file->mime_type === 'image/svg+xml';

        $response = $conversion !== '' || $isSvg || in_array($file->mime_type, self::INLINE_TYPES, true)
            ? $file->toInlineResponse($request, $conversion)
            : $file->toResponse($request, $conversion);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if ($isSvg) {
            // Fine as an <img>, but opened on its own an SVG can run script:
            // this makes it inert there too.
            $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
        }

        return $response;
    }
}

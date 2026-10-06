<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Single pictures attached to a record - a person's avatar, a customer or
 * business partner's logo, a material's photo. One file per record, always
 * shown as a square (cropped from the centre), and streamed through the app
 * rather than linked straight to the disk so access follows the viewer's
 * permissions and it works the same on local disk and the S3 bucket.
 */
final class Images
{
    public const MAX_KILOBYTES = 5120;

    public const CONVERSION = 'square';

    /** Raster only - SVG can carry script, and the square crop needs a bitmap. */
    public const MIMES = 'jpg,jpeg,png,gif,webp';

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['nullable', 'file', 'mimes:'.self::MIMES, 'max:'.self::MAX_KILOBYTES];
    }

    public static function collection(HasMedia $model, string $collection): void
    {
        $model->addMediaCollection($collection)->singleFile();
    }

    /**
     * The square crop, made as the file is stored (synchronously, so it
     * exists by the time the upload's response reaches the browser).
     */
    public static function conversion(HasMedia $model, string $collection): void
    {
        $model->addMediaConversion(self::CONVERSION)
            ->fit(Fit::Crop, 256, 256)
            ->nonQueued()
            ->performOnCollections($collection);
    }

    /**
     * Apply a form's `$field` upload and `remove_{$field}` flag to the model.
     * A new upload replaces the old one (the collection is single-file).
     */
    public static function sync(HasMedia $model, Request $request, string $field, string $collection): void
    {
        $file = $request->file($field);

        if ($file instanceof UploadedFile) {
            $model->addMedia($file)->toMediaCollection($collection);
        } elseif ($request->boolean("remove_{$field}")) {
            $model->clearMediaCollection($collection);
        }
    }

    /**
     * Where the browser loads the picture from, or null when there is none.
     * The media id in the query string changes whenever the picture does, so
     * the response can be cached hard without ever showing a stale one.
     * Pass a model loaded with its `media` relation when listing many.
     */
    public static function url(HasMedia&Model $model, string $collection, string $route): ?string
    {
        $media = $model->getFirstMedia($collection);

        return $media ? route($route, $model).'?v='.$media->id : null;
    }

    public static function respond(Request $request, HasMedia $model, string $collection): StreamedResponse
    {
        $media = $model->getFirstMedia($collection);

        abort_if($media === null, 404);

        $conversion = $media->hasGeneratedConversion(self::CONVERSION) ? self::CONVERSION : '';

        $response = $media->toInlineResponse($request, $conversion);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, max-age=86400');

        return $response;
    }
}

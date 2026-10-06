<?php

namespace App\Support;

use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The one 3D / CAD file a tool can carry (a bit's or blade's geometry, later
 * the basis for a cut profile). Separate from its photo: that is a picture
 * shown in lists, this is a model that is only ever opened or downloaded.
 * CAD formats have no reliable mime type, so the extension decides - the same
 * set `Attachments::kind()` knows how to classify.
 */
final class ToolModels
{
    public const COLLECTION = 'model';

    public const MAX_MEGABYTES = 30;

    public const MAX_KILOBYTES = self::MAX_MEGABYTES * 1024;

    public const EXTENSIONS = 'step,stp,stl,3mf,obj,dxf,dwg';

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['nullable', 'file', 'extensions:'.self::EXTENSIONS, 'max:'.self::MAX_KILOBYTES];
    }

    /**
     * Apply a form's `model` upload and `remove_model` flag. A new upload
     * replaces the old one (the collection is single-file).
     */
    public static function sync(Tool $tool, Request $request): void
    {
        $file = $request->file('model');

        if ($file instanceof UploadedFile) {
            $tool->addMedia($file)->toMediaCollection(self::COLLECTION);
        } elseif ($request->boolean('remove_model')) {
            $tool->clearMediaCollection(self::COLLECTION);
        }
    }

    /**
     * The file's shape for the frontend (same keys as an attachment, so the
     * shared row and 3D viewer work on it), or null when there is none.
     *
     * @return array<string, mixed>|null
     */
    public static function props(Tool $tool): ?array
    {
        $media = $tool->getFirstMedia(self::COLLECTION);

        return $media ? [
            'id' => $media->id,
            'name' => $media->file_name,
            'size' => $media->size,
            'kind' => Attachments::kind($media),
            'file_path' => route('tools.model', $tool).'?v='.$media->id,
        ] : null;
    }

    public static function media(Tool $tool): Media
    {
        return $tool->getFirstMedia(self::COLLECTION) ?? abort(404);
    }
}

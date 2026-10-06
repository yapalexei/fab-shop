<?php

namespace App\Support;

use App\Models\Certification;

/**
 * The scan or photo of the actual certificate, kept with the record so it
 * has something physical behind it. Deliberately narrower than quote
 * attachments: only PDFs and images (both previewable in the browser), a
 * handful per certification, but allowed to be larger since a scanned
 * certificate often is.
 */
final class CertificationDocuments
{
    public const COLLECTION = 'documents';

    public const MAX_FILES = 3;

    public const MAX_MEGABYTES = 30;

    public const MAX_KILOBYTES = self::MAX_MEGABYTES * 1024;

    public const MIMES = 'pdf,jpg,jpeg,png,gif,webp';

    /**
     * Rules for a `documents` field holding up to $limit new files.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(int $limit = self::MAX_FILES): array
    {
        return [
            'documents' => ['nullable', 'array', 'max:'.$limit],
            'documents.*' => ['file', 'mimes:'.self::MIMES, 'max:'.self::MAX_KILOBYTES],
        ];
    }

    public static function room(Certification $certification): int
    {
        return max(0, self::MAX_FILES - $certification->getMedia(self::COLLECTION)->count());
    }
}

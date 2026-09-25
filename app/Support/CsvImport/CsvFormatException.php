<?php

namespace App\Support\CsvImport;

use RuntimeException;

/**
 * The file itself couldn't be read as the expected CSV - missing/unreadable
 * file, or a header row missing one of the resource's required columns.
 * Rejected before any row is touched, never partially imported.
 */
class CsvFormatException extends RuntimeException {}

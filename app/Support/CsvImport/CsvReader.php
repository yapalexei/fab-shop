<?php

namespace App\Support\CsvImport;

/**
 * Parses an uploaded CSV into rows keyed by the importer's expected column
 * names - matched case-insensitively and independent of column order (a
 * shop reordering columns in Excel shouldn't break the import), but every
 * expected column must be present, or the whole file is rejected upfront.
 *
 * When the owner has mapped the file's columns by hand (supplier exports
 * rarely match a template), `$mapping` says which file column feeds each
 * template column instead; a template column mapped to nothing reads as
 * blank.
 */
class CsvReader
{
    /**
     * @param  array<int, string>  $expectedColumns
     * @param  array<string, int|null>|null  $mapping  template column => zero-based file column, or null to leave it blank; null here means match by header name
     * @return array<int, array<string, string>>
     *
     * @throws CsvFormatException
     */
    public static function parse(string $absolutePath, array $expectedColumns, ?array $mapping = null): array
    {
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new CsvFormatException('The uploaded file could not be read.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === null) {
                throw new CsvFormatException('The file is empty.');
            }

            // name => column index, matched case-insensitively.
            $normalizedHeader = array_map(fn (?string $column) => mb_strtolower(trim((string) $column)), $header);
            $columnIndex = [];

            foreach ($expectedColumns as $column) {
                if ($mapping !== null) {
                    $chosen = $mapping[$column] ?? null;

                    if ($chosen !== null && ! array_key_exists($chosen, $header)) {
                        throw new CsvFormatException("The column chosen for \"{$column}\" isn't in the file.");
                    }

                    $columnIndex[$column] = $chosen;

                    continue;
                }

                $index = array_search(mb_strtolower($column), $normalizedHeader, true);

                if ($index === false) {
                    throw new CsvFormatException("Missing required column \"{$column}\". Download the template to see the expected columns.");
                }

                $columnIndex[$column] = $index;
            }

            $rows = [];

            while (($line = fgetcsv($handle)) !== false) {
                // A blank trailing line (common when a spreadsheet app saves
                // the file) parses as [null] - skip it rather than reporting
                // a phantom empty row.
                if ($line === [null] || $line === []) {
                    continue;
                }

                $row = [];

                foreach ($columnIndex as $column => $index) {
                    $row[$column] = $index === null ? '' : trim((string) ($line[$index] ?? ''));
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * The header row and the first few data rows, for the mapping step.
     *
     * @return array{headers: array<int, string>, sample: array<int, array<int, string>>}
     *
     * @throws CsvFormatException
     */
    public static function preview(string $absolutePath, int $sampleRows = 3): array
    {
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new CsvFormatException('The uploaded file could not be read.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === null) {
                throw new CsvFormatException('The file is empty.');
            }

            $sample = [];

            while (count($sample) < $sampleRows && ($line = fgetcsv($handle)) !== false) {
                if ($line === [null] || $line === []) {
                    continue;
                }

                $sample[] = array_map(fn (?string $cell) => trim((string) $cell), $line);
            }

            return [
                'headers' => array_map(fn (?string $column) => trim((string) $column), $header),
                'sample' => $sample,
            ];
        } finally {
            fclose($handle);
        }
    }
}

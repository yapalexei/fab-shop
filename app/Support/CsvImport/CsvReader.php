<?php

namespace App\Support\CsvImport;

/**
 * Parses an uploaded CSV into rows keyed by the importer's expected column
 * names - matched case-insensitively and independent of column order (a
 * shop reordering columns in Excel shouldn't break the import), but every
 * expected column must be present, or the whole file is rejected upfront.
 */
class CsvReader
{
    /**
     * @param  array<int, string>  $expectedColumns
     * @return array<int, array<string, string>>
     *
     * @throws CsvFormatException
     */
    public static function parse(string $absolutePath, array $expectedColumns): array
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
                    $row[$column] = trim((string) ($line[$index] ?? ''));
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}

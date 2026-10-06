<?php

namespace App\Support\CsvImport;

class ImporterRegistry
{
    /**
     * @return array<int, class-string<ResourceImporter>>
     */
    private static function classes(): array
    {
        return [
            CustomerImporter::class,
            MaterialImporter::class,
            ServiceImporter::class,
            BusinessPartnerImporter::class,
            EquipmentImporter::class,
            ToolImporter::class,
            MaterialTypeImporter::class,
            SupplierPriceImporter::class,
        ];
    }

    public static function has(string $key): bool
    {
        return self::find($key) !== null;
    }

    public static function for(string $key): ResourceImporter
    {
        $importer = self::find($key);

        abort_unless($importer, 404);

        return $importer;
    }

    private static function find(string $key): ?ResourceImporter
    {
        foreach (self::classes() as $class) {
            $importer = new $class;

            if ($importer->key() === $key) {
                return $importer;
            }
        }

        return null;
    }

    /**
     * @return array<int, ResourceImporter>
     */
    public static function all(): array
    {
        return array_map(fn (string $class) => new $class, self::classes());
    }
}

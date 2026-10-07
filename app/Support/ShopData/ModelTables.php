<?php

namespace App\Support\ShopData;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Which Eloquent model lives in which table, so the polymorphic rows with no
 * foreign key (uploaded files in `media`, entries in the activity log) can be
 * matched to the shop's records by their stored class name.
 */
class ModelTables
{
    /**
     * @return array<string, class-string<Model>> table => model class
     */
    public static function all(): array
    {
        $map = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $map[(new $class)->getTable()] = $class;
        }

        return $map;
    }

    /**
     * @return array<string, class-string<Model&HasMedia>>
     */
    public static function withMedia(): array
    {
        return array_filter(self::all(), fn (string $class) => is_subclass_of($class, HasMedia::class));
    }
}

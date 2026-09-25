<?php

namespace App\Enums;

enum MaterialUsageType: string
{
    case Used = 'used';
    case Scrap = 'scrap';

    public function label(): string
    {
        return match ($this) {
            self::Used => 'Used',
            self::Scrap => 'Scrap',
        };
    }
}

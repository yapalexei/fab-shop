<?php

namespace App\Enums;

/**
 * The material families a tool can be rated to cut - so a "1/4in drill bit"
 * for steel and one for aluminum are distinct catalog entries, clearly
 * labelled wherever they're picked.
 */
enum CuttableMaterial: string
{
    case CarbonSteel = 'carbon-steel';
    case StainlessSteel = 'stainless-steel';
    case Aluminum = 'aluminum';
    case CastIron = 'cast-iron';
    case CopperBrass = 'copper-brass';
    case Titanium = 'titanium';
    case Plastic = 'plastic';
    case Wood = 'wood';

    public function label(): string
    {
        return match ($this) {
            self::CarbonSteel => 'Carbon steel',
            self::StainlessSteel => 'Stainless steel',
            self::Aluminum => 'Aluminum',
            self::CastIron => 'Cast iron',
            self::CopperBrass => 'Copper & brass',
            self::Titanium => 'Titanium',
            self::Plastic => 'Plastic',
            self::Wood => 'Wood',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $material) => ['value' => $material->value, 'label' => $material->label()], self::cases());
    }
}

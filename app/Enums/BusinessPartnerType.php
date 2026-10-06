<?php

namespace App\Enums;

/**
 * What a business partner is to the shop. A partner can be both: the same
 * company may sell material and also do the finishing.
 */
enum BusinessPartnerType: string
{
    case Supplier = 'supplier';
    case ServiceProvider = 'service_provider';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Supplier / vendor',
            self::ServiceProvider => 'Service provider',
            self::Both => 'Supplier & service provider',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}

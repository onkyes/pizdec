<?php

declare(strict_types=1);

namespace App\Enum;

enum DeliveryType: string
{
    case Pickup = 'pickup'; // самовывоз
    case Courier = 'courier'; // доставка курьером

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $type): string => $type->value, // берём строковое значение каждого enum
            self::cases(), // получаем все варианты enum
        );
    }
}

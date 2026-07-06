<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductCategory: string
{
    case Food = 'food';
    case Drink = 'drink';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $category): string => $category->value,
            self::cases(),
        );
    }
}

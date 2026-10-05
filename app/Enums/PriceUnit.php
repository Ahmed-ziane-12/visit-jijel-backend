<?php

namespace App\Enums;

enum PriceUnit: string
{
    case Night = 'night';
    case Person = 'person';
    case Item = 'item';
    case Stay = 'stay';
    case Day = 'day';
    case SquareMeter = 'm2';
    case Total = 'total';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Night => 'Per night',
            self::Person => 'Per person',
            self::Item => 'Per item',
            self::Stay => 'Per stay',
            self::Day => 'Per day',
            self::SquareMeter => 'Per m²',
            self::Total => 'Total price',
        };
    }
}

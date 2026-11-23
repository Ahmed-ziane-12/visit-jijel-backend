<?php

namespace App\Enums;

enum DestinationCategory: string
{
    case Nature = 'nature';
    case Historical = 'historical';
    case Beach = 'beach';
    case Urban = 'urban';
    case Cultural = 'cultural';
    case Sport = 'sport';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

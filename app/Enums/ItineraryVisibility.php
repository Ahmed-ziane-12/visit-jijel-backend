<?php

namespace App\Enums;

enum ItineraryVisibility: string
{
    case Private = 'private';
    case Public = 'public';
    case Shared = 'shared';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

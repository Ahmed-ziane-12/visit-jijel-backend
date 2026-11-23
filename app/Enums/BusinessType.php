<?php

namespace App\Enums;

enum BusinessType: string
{
    case Restaurant = 'restaurant';
    case TouristicAgency = 'touristic_agency';
    case RealEstateAgency = 'real_estate_agency';
    case Hotel = 'hotel';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

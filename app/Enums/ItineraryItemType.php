<?php

namespace App\Enums;

enum ItineraryItemType: string
{
    case Destination = 'destination';
    case Listing = 'listing';
    case Event = 'event';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

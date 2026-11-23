<?php

namespace App\Enums;

enum CalendarEventSource: string
{
    case Manual = 'manual';
    case Itinerary = 'itinerary';
    case Event = 'event';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

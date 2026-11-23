<?php

namespace App\Enums;

enum MediaResourceType: string
{
    case Image = 'image';
    case Video = 'video';
    case Raw = 'raw';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum MediaCollection: string
{
    case Default = 'default';
    case Cover = 'cover';
    case Covers = 'covers';
    case Gallery = 'gallery';
    case Avatar = 'avatar';
    case Profiles = 'profiles';
    case Posts = 'posts';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum UserRole: string
{
    case BusinessOwner = 'business_owner';
    case Client = 'client';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

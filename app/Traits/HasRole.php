<?php

namespace App\Traits;

/**
 * @property string $role
 */
trait HasRole
{
    public function isBusinessOwner(): bool
    {
        return $this->role === 'business_owner';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}

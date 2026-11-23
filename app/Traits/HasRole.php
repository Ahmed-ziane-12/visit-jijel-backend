<?php

namespace App\Traits;

use App\Enums\UserRole;

/**
 * @property UserRole $role
 */
trait HasRole
{
    public function isBusinessOwner(): bool
    {
        return $this->role === UserRole::BusinessOwner;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }
}

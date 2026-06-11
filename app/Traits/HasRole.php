<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $role
 */
trait HasRole
{
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBusinessOwner(): bool
    {
        return $this->role === 'business_owner';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    public function scopeBusinessOwners(Builder $query): Builder
    {
        return $query->where('role', 'business_owner');
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->where('role', 'client');
    }
}

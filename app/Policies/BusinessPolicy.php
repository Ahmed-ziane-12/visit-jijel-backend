<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Business $business): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function update(User $user, Business $business): bool
    {
        return $user->id === $business->owner_id;
    }

    public function delete(User $user, Business $business): bool
    {
        return $user->id === $business->owner_id;
    }
}

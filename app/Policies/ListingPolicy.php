<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Listing $listing): bool
    {
        return true;
    }

    public function create(User $user, Business $business): bool
    {
        return $user->id === $business->owner_id;
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->id === $listing->business?->owner_id;
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $user->id === $listing->business?->owner_id;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nested author payload for posts, comments and reviews.
 *
 * Deliberately narrower than UserResource: other people's email addresses and
 * admin flags are never part of a public feed response.
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'profile' => $this->whenLoaded('profile', fn () => new ProfileResource($this->profile)),
        ];
    }
}

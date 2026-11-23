<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit whitelist for user payloads.
 *
 * Returning the Eloquent model directly leaked internal flags such as
 * is_admin, is_super_admin, must_reset_password, created_by and
 * email_verified_at on public profiles. Only the owner of the account should
 * receive the private fields.
 */
class UserResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includePrivate = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'email' => $this->when($this->includePrivate, $this->email),
            'email_verified_at' => $this->when($this->includePrivate, $this->email_verified_at),
            'profile' => $this->whenLoaded('profile', fn () => new ProfileResource($this->profile)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

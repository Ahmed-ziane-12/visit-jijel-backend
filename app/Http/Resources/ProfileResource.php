<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile payload.
 *
 * The avatar accessor already returns a Cloudinary URL; the media relation is
 * only emitted when it has been eager loaded so lists do not trigger N+1s.
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'phone' => $this->phone,
            'bio' => $this->bio,
            'wilaya' => $this->wilaya,
            'commune' => $this->commune,
            'avatar' => $this->avatar,
            'media' => $this->whenLoaded('media', fn () => MediaResource::collection($this->media)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

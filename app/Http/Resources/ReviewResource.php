<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'listing_id' => $this->listing_id,
            'destination_id' => $this->destination_id,
            'event_id' => $this->event_id,
            'rating' => $this->rating,
            'body' => $this->body,
            'is_approved' => $this->is_approved,
            'user' => $this->whenLoaded('user', fn () => new UserSummaryResource($this->user)),
            'listing' => $this->whenLoaded('listing'),
            'destination' => $this->whenLoaded('destination'),
            'event' => $this->whenLoaded('event'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

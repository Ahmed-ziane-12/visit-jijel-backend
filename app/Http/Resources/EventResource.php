<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'destination_id' => $this->destination_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'price' => $this->price,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'max_attendees' => $this->max_attendees,
            'status' => $this->status,
            'business' => $this->whenLoaded('business'),
            'destination' => $this->whenLoaded('destination'),
            'creator' => $this->whenLoaded('creator', fn () => new UserSummaryResource($this->creator)),
            'media' => $this->whenLoaded('media', fn () => MediaResource::collection($this->media)),
            'reviews' => $this->whenLoaded('reviews', fn () => ReviewResource::collection($this->reviews)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

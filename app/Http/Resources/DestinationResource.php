<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arabic_name' => $this->arabic_name,
            'description' => $this->description,
            'arabic_description' => $this->arabic_description,
            'address' => $this->address,
            'arabic_address' => $this->arabic_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'category' => $this->category,
            'arabic_category' => $this->arabic_category,
            'is_featured' => $this->is_featured,
            'tags' => $this->tags,
            'state' => $this->state,
            'reviews_count' => $this->when(
                array_key_exists('reviews_count', $this->resource->getAttributes()),
                fn () => $this->reviews_count,
            ),
            'reviews_avg_rating' => $this->when(
                array_key_exists('reviews_avg_rating', $this->resource->getAttributes()),
                fn () => $this->reviews_avg_rating,
            ),
            'events' => $this->whenLoaded('events', fn () => EventResource::collection($this->events)),
            'reviews' => $this->whenLoaded('reviews', fn () => ReviewResource::collection($this->reviews)),
            'media' => $this->whenLoaded('media', fn () => MediaResource::collection($this->media)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

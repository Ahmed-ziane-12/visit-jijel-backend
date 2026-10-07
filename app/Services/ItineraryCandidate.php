<?php

namespace App\Services;

class ItineraryCandidate
{
    public float $score = 0.0;

    /**
     * @param  array<int, string>  $keywords  canonical interest keywords matched by this candidate
     * @param  string|null  $category  canonical category keyword of the candidate (destinations/events)
     * @param  string|null  $eventDate  Y-m-d date the event starts on (events only)
     * @param  string|null  $eventEndDate  Y-m-d date the event ends on (events only)
     * @param  string|null  $image  cover image url when available
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly float $latitude = 0.0,
        public readonly float $longitude = 0.0,
        public readonly ?float $price = null,
        public readonly float $rating = 0.0,
        public readonly int $reviewCount = 0,
        public readonly bool $featured = false,
        public readonly array $keywords = [],
        public readonly ?string $category = null,
        public readonly ?string $eventDate = null,
        public readonly ?string $eventEndDate = null,
        public readonly ?string $image = null,
    ) {}

    public function key(): string
    {
        return "{$this->type}:{$this->id}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_type' => $this->type,
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'price' => $this->price,
            'rating' => $this->rating,
            'score' => round($this->score, 2),
            'image_url' => $this->image,
        ];
    }
}

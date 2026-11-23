<?php

namespace App\Traits;

use App\Enums\MediaCollection;

trait HasCoverImage
{
    /**
     * Resolve the cover from the media table: an explicit cover first, then
     * the first gallery item.
     *
     * When the `media` relation is already loaded the accessor works on the
     * in-memory collection instead of issuing a query per model.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (! method_exists($this, 'media')) {
            return null;
        }

        if ($this->relationLoaded('media')) {
            $cover = $this->media->firstWhere('is_cover', true)
                ?? $this->media
                    ->where('collection', MediaCollection::Gallery)
                    ->sortBy('sort_order')
                    ->first();

            return $cover?->secure_url;
        }

        $cover = $this->media()
            ->where('is_cover', true)
            ->first()
            ?? $this->media()
                ->where('collection', MediaCollection::Gallery)
                ->orderBy('sort_order')
                ->first();

        return $cover?->secure_url;
    }
}

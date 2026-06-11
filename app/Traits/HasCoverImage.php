<?php

namespace App\Traits;

/**
 * @property string|null $cover_image
 */
trait HasCoverImage
{
    public function getCoverImageUrlAttribute(): ?string
    {
        // First check the dedicated cover_image column (for events/destinations)
        if (! empty($this->cover_image)) {
            return $this->cover_image;
        }

        // Fall back to the media table cover
        if (method_exists($this, 'media')) {
            $cover = $this->media()
                ->where('is_cover', true)
                ->first()
                ?? $this->media()
                    ->where('collection', 'gallery')
                    ->orderBy('sort_order')
                    ->first();

            return $cover?->secure_url;
        }

        return null;
    }
}

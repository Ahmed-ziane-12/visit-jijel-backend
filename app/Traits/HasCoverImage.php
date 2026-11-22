<?php

namespace App\Traits;

trait HasCoverImage
{
    /**
     * Resolve the cover from the media table: an explicit cover first, then
     * the first gallery item.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (! method_exists($this, 'media')) {
            return null;
        }

        $cover = $this->media()
            ->where('is_cover', true)
            ->first()
            ?? $this->media()
                ->where('collection', 'gallery')
                ->orderBy('sort_order')
                ->first();

        return $cover?->secure_url;
    }
}

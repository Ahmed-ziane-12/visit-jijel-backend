<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 *
 * @mixin Model
 *
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany morphMany(string $related, string $name, string $type = null, string $id = null, string $localKey = null)
 */
trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function cover(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')
            ->where('is_cover', true)
            ->limit(1);
    }

    public function gallery(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')
            ->where('collection', 'gallery')
            ->orderBy('sort_order');
    }

    public function getMediaInCollection(string $collection): MorphMany
    {
        return $this->morphMany(Media::class, 'model')
            ->where('collection', $collection);
    }

    /**
     * Called after Cloudinary confirms the upload.
     * Stores the final media record using the webhook/frontend payload.
     */
    public function attachMedia(array $cloudinaryResponse, string $collection = 'default', bool $isCover = false): Media
    {
        if ($isCover) {
            $this->getMediaInCollection($collection)->update(['is_cover' => false]);
        }

        return $this->media()->create([
            'model_type' => static::class,
            'model_id' => $this->id,
            'cloudinary_public_id' => $cloudinaryResponse['cloudinary_public_id'], // ← was 'public_id'
            'url' => $cloudinaryResponse['url'],
            'secure_url' => $cloudinaryResponse['secure_url'],
            'format' => $cloudinaryResponse['format'] ?? null,
            'resource_type' => $cloudinaryResponse['resource_type'] ?? 'image',
            'width' => $cloudinaryResponse['width'] ?? null,
            'height' => $cloudinaryResponse['height'] ?? null,
            'size' => $cloudinaryResponse['bytes'] ?? null,
            'collection' => $collection,
            'is_cover' => $isCover,
            'sort_order' => $this->media()->where('collection', $collection)->count(),
        ]);
    }

    /**
     * Detach and delete a media record.
     * Note: does NOT delete from Cloudinary — handle that in a job or webhook.
     */
    public function detachMedia(string $cloudinaryPublicId): void
    {
        $this->media()->where('cloudinary_public_id', $cloudinaryPublicId)->delete();
    }

    public function clearMediaCollection(string $collection = 'default'): void
    {
        $mediaItems = $this->getMediaInCollection($collection)->get();

        foreach ($mediaItems as $media) {
            $media->delete();
        }
    }
}

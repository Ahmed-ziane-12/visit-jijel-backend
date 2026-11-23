<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit media payload.
 *
 * Mirrors the media table columns so the JSON stays identical to the model
 * serialization while giving us one place to extend the contract.
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'cloudinary_public_id' => $this->cloudinary_public_id,
            'url' => $this->url,
            'secure_url' => $this->secure_url,
            'format' => $this->format,
            'resource_type' => $this->resource_type,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'collection' => $this->collection,
            'is_cover' => $this->is_cover,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}

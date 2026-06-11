<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'model_type',
        'model_id',
        'cloudinary_public_id',
        'url',
        'secure_url',
        'format',
        'resource_type',
        'width',
        'height',
        'size',
        'collection',
        'is_cover',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Helpers ───────────────────────────────────────────────
    public function delete(): ?bool
    {
        return parent::delete();
    }

    public function isImage(): bool
    {
        return $this->resource_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->resource_type === 'video';
    }

    /**
     * Generate a Cloudinary transformation URL on the fly.
     * e.g. $media->transform('w_800,h_600,c_fill')
     */
    public function transform(string $transformations): string
    {
        // Insert transformation string into the Cloudinary URL
        // https://res.cloudinary.com/{cloud}/image/upload/{transform}/{public_id}
        return preg_replace(
            '/\/upload\//',
            '/upload/'.$transformations.'/',
            $this->secure_url,
            1
        );
    }

    public function thumbnail(int $width = 400, int $height = 300): string
    {
        return $this->transform("w_{$width},h_{$height},c_fill,f_auto,q_auto");
    }
}

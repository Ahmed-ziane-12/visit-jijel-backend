<?php

namespace App\Models;

use App\Traits\HasCoverImage;
use App\Traits\HasLocation;
use App\Traits\HasMedia;
use App\Traits\HasReviews;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    use HasCoverImage, HasFactory, HasLocation, HasMedia, HasReviews;

    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'category',
        'is_featured',
        'tags',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_featured' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class);
    }

    public function delete(): ?bool
    {
        return parent::delete();
    }
}

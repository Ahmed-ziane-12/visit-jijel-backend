<?php

namespace App\Models;

use App\Traits\HasCoverImage;
use App\Traits\HasMedia;
use App\Traits\HasReviews;
use App\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use HasCoverImage, HasFactory, HasMedia, HasReviews, HasStatus;

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'price',
        'currency',
        'amenities',
        'capacity',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'amenities' => 'array',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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

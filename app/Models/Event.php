<?php

namespace App\Models;

use App\Traits\HasCoverImage;
use App\Traits\HasLocation;
use App\Traits\HasMedia;
use App\Traits\HasReviews;
use App\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasCoverImage, HasFactory, HasLocation, HasMedia, HasReviews, HasStatus;

    protected $fillable = [
        'business_id',
        'destination_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'price',
        'location',
        'latitude',
        'longitude',
        'max_attendees',
        'cover_image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function delete(): ?bool
    {
        return parent::delete();
    }
}

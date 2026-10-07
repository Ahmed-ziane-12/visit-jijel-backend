<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'itinerary_day_id',
        'destination_id',
        'listing_id',
        'event_id',
        'title',
        'notes',
        'start_time',
        'end_time',
        'sort_order',
        'item_type',
    ];

    protected $appends = ['description', 'image_url', 'latitude', 'longitude'];

    public function getDescriptionAttribute(): ?string
    {
        return $this->notes;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->destination?->media?->first()?->secure_url;
    }

    /**
     * Coordinates of the underlying entity so day maps can plot server items.
     */
    public function getLatitudeAttribute(): ?float
    {
        $latitude = match ($this->item_type) {
            'destination' => $this->destination?->latitude,
            'listing' => $this->listing?->business?->latitude,
            'event' => $this->event?->latitude ?? $this->event?->business?->latitude,
            default => null,
        };

        return $latitude !== null ? (float) $latitude : null;
    }

    public function getLongitudeAttribute(): ?float
    {
        $longitude = match ($this->item_type) {
            'destination' => $this->destination?->longitude,
            'listing' => $this->listing?->business?->longitude,
            'event' => $this->event?->longitude ?? $this->event?->business?->longitude,
            default => null,
        };

        return $longitude !== null ? (float) $longitude : null;
    }

    // ── Relationships ─────────────────────────────────────────
    public function day(): BelongsTo
    {
        return $this->belongsTo(ItineraryDay::class, 'itinerary_day_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function delete(): ?bool
    {
        return parent::delete();
    }

    /**
     * Resolve the actual subject of this item regardless of type.
     */
    public function resolvable(): BelongsTo
    {
        return match ($this->item_type) {
            'destination' => $this->destination(),
            'listing' => $this->listing(),
            'event' => $this->event(),
            default => $this->destination(),
        };
    }
}

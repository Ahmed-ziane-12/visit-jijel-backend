<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItineraryDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'itinerary_id',
        'day_date',
        'day_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'day_date' => 'date',
        ];
    }

    // ── Relationships ─────────────────────────────────────────
    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('sort_order');
    }

    public function delete(): ?bool
    {
        return parent::delete();
    }
}

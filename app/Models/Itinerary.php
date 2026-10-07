<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Itinerary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'notes',
        'start_date',
        'end_date',
        'visibility',
        'status',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'preferences' => 'array',
        ];
    }

    // ── Relationships ─────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ItineraryDay::class)->orderBy('day_number');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(ItineraryItem::class, ItineraryDay::class);
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

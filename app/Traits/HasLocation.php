<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $wilaya
 */
trait HasLocation
{
    /**
     * Scope to filter models within a radius (km) from a point.
     * Uses the Haversine formula.
     */
    public function scopeNearby(
        Builder $query,
        float $latitude,
        float $longitude,
        float $radiusKm = 10
    ): Builder {
        return $query->selectRaw('
                *,
                (6371 * acos(
                    cos(radians(?)) *
                    cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(latitude))
                )) AS distance
            ', [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance');
    }

    /**
     * Scope to filter by wilaya — only for models that have that column.
     */
    public function scopeInWilaya(Builder $query, string $wilaya): Builder
    {
        return $query->where('wilaya', $wilaya);
    }

    public function hasCoordinates(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    public function getCoordinates(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}

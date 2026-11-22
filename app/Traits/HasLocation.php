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
     * Filter models within a radius (km) from a point using the Haversine
     * formula.
     *
     * The distance expression is repeated in WHERE and ORDER BY instead of
     * being referenced by its SELECT alias: PostgreSQL does not allow column
     * aliases in WHERE/HAVING, and an alias in HAVING without GROUP BY would
     * turn the query into an invalid aggregate.
     */
    public function scopeNearby(
        Builder $query,
        float $latitude,
        float $longitude,
        float $radiusKm = 10
    ): Builder {
        $expression = '(6371 * acos(least(1, greatest(-1,
            cos(radians(?)) * cos(radians(latitude)) *
            cos(radians(longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(latitude))
        ))))';

        $bindings = [$latitude, $longitude, $latitude];

        return $query
            ->selectRaw("*, {$expression} AS distance", $bindings)
            ->whereRaw("{$expression} <= ?", [...$bindings, $radiusKm])
            ->orderByRaw("{$expression} ASC", $bindings);
    }

    public function hasCoordinates(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }
}

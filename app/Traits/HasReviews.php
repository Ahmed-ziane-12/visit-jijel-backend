<?php

namespace App\Traits;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasReviews
{
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function getAverageRatingAttribute(): float
    {
        return round($this->approvedReviews()->avg('rating') ?? 0, 1);
    }

    public function getReviewCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    /**
     * Filter and order by the approved-review average.
     *
     * The alias created by withAvg() cannot be used inside HAVING on
     * PostgreSQL, so the minimum is enforced with an explicit correlated
     * subquery while ORDER BY still uses the SELECT alias (which PostgreSQL
     * does allow).
     */
    public function scopeTopRated(Builder $query, float $minimum = 4.0): Builder
    {
        $model = $query->getModel();
        $relation = $model->approvedReviews();

        $reviewsTable = $relation->getRelated()->getTable();
        $foreignKey = $relation->getForeignKeyName();
        $table = $model->getTable();

        return $query
            ->withAvg('approvedReviews', 'rating')
            ->whereRaw(
                "(select avg({$reviewsTable}.rating)
                    from {$reviewsTable}
                    where {$reviewsTable}.{$foreignKey} = {$table}.id
                      and {$reviewsTable}.is_approved = true) >= ?",
                [$minimum]
            )
            ->orderByDesc('approved_reviews_avg_rating');
    }
}

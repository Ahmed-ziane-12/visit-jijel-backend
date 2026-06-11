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

    public function scopeTopRated(Builder $query, float $minimum = 4.0): Builder
    {
        return $query->withAvg('approvedReviews', 'rating')
            ->having('approved_reviews_avg_rating', '>=', $minimum)
            ->orderByDesc('approved_reviews_avg_rating');
    }
}

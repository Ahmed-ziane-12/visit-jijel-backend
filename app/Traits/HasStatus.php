<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $status
 */
trait HasStatus
{
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(): bool
    {
        return $this->update(['status' => 'published']);
    }

    public function archive(): bool
    {
        return $this->update(['status' => 'archived']);
    }
}

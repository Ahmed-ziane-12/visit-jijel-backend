<?php

namespace App\Traits;

use BackedEnum;
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

    public function isPublished(): bool
    {
        return $this->statusValue() === 'published';
    }

    public function isDraft(): bool
    {
        return $this->statusValue() === 'draft';
    }

    public function publish(): bool
    {
        return $this->update(['status' => 'published']);
    }

    /**
     * The trait is shared by models whose status column maps to different
     * enums, so compare against the backing value instead of a fixed case.
     */
    private function statusValue(): string
    {
        return $this->status instanceof BackedEnum
            ? $this->status->value
            : (string) $this->status;
    }
}

<?php

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Post extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'user_id',
        'body',
        'shareable_type',
        'shareable_id',
        'parent_post_id',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_post_id');
    }

    public function childPosts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_post_id');
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    // ── Helpers ───────────────────────────────────────────────
    public function isSharedPost(): bool
    {
        return $this->parent_post_id !== null;
    }

    public function isSharedItem(): bool
    {
        return $this->shareable_type !== null;
    }

    public function likesCount(): int
    {
        return $this->likes()->where('type', 'like')->count();
    }

    public function dislikesCount(): int
    {
        return $this->likes()->where('type', 'dislike')->count();
    }

    public function commentsCount(): int
    {
        return $this->comments()->count();
    }

    public function delete(): ?bool
    {
        $this->comments()->delete();
        $this->likes()->delete();

        return parent::delete();
    }
}

<?php

namespace App\Models;

use App\Enums\MediaCollection;
use App\Enums\UserRole;
use App\Traits\HasMedia;
use App\Traits\HasRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory, HasMedia, HasRole;

    protected $appends = ['avatar'];

    protected $fillable = [
        'user_id',
        'role',
        'phone',
        'bio',
        'wilaya',
        'commune',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAvatarAttribute(): ?string
    {
        // Prefer the eager-loaded relation so serializing a list of profiles
        // does not issue one query per row.
        if ($this->relationLoaded('media')) {
            return $this->media
                ->firstWhere('collection', MediaCollection::Profiles)
                ?->secure_url;
        }

        return $this->media()
            ->where('collection', MediaCollection::Profiles)
            ->first()
            ?->secure_url;
    }
}

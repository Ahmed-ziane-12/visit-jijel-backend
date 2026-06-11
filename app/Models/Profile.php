<?php

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\HasRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory, HasMedia, HasRole;

    protected $fillable = [
        'user_id',
        'role',
        'phone',
        'bio',
        'wilaya',
        'commune',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->media()
            ->where('collection', 'avatar')
            ->first()
            ?->secure_url;
    }
}

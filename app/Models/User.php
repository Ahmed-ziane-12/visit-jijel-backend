<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmail;
use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_super_admin',
        'must_reset_password',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = ['profile'];

    protected $appends = ['role'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'must_reset_password' => 'boolean',
        ];
    }

    // ── Admin helpers ─────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->is_admin || $this->is_super_admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function mustResetPassword(): bool
    {
        return $this->must_reset_password;
    }

    // ── Canonical role ────────────────────────────────────────
    public function getRoleAttribute(): string
    {
        if ($this->is_super_admin) {
            return 'super_admin';
        }

        if ($this->is_admin) {
            return 'admin';
        }

        return $this->profile?->role ?? 'client';
    }

    // ── Role helpers (delegates to profile for clients/owners) ─
    public function isBusinessOwner(): bool
    {
        return $this->profile?->role === 'business_owner';
    }

    public function isClient(): bool
    {
        return $this->profile?->role === 'client';
    }

    // ── Relationships ─────────────────────────────────────────
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdAdmins(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function delete(): ?bool
    {
        return parent::delete();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * Strip control / invisible characters that break address handling.
     */
    protected function cleanEmail(string $email): string
    {
        $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);

        return trim((string) $email);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : $this->cleanEmail($value);
    }

    /**
     * Route notifications to a plain string email so Laravel's mail channel
     * and transport can build recipient addresses cleanly. The transport
     * supplies a recipient name fallback when none is provided.
     */
    public function routeNotificationFor(string $driver, $notification = null): mixed
    {
        if ($driver === 'mail') {
            return $this->cleanEmail((string) $this->email);
        }

        return parent::routeNotificationFor($driver, $notification);
    }
}

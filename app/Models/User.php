<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmail;
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

    public function delete(): ?bool
    {
        return parent::delete();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmail);
    }
}

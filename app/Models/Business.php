<?php

namespace App\Models;

use App\Traits\HasLocation;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory, HasLocation, HasMedia;

    protected $fillable = [
        'owner_id',
        'type',
        'name',
        'description',
        'phone',
        'email',
        'website',
        'address',
        'latitude',
        'longitude',
        'wilaya',
        'commune',
        'is_verified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function delete(): ?bool
    {
        return parent::delete();
    }
}

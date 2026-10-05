<?php

namespace App\Models;

use App\Enums\PriceUnit;
use Database\Factories\BusinessDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDetail extends Model
{
    /** @use HasFactory<BusinessDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'average_price',
        'price_unit',
        'number_of_rooms',
        'star_rating',
        'cuisine_type',
        'seating_capacity',
        'amenities',
        'services',
    ];

    protected function casts(): array
    {
        return [
            'average_price' => 'decimal:2',
            'price_unit' => PriceUnit::class,
            'number_of_rooms' => 'integer',
            'star_rating' => 'integer',
            'seating_capacity' => 'integer',
            'amenities' => 'array',
            'services' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

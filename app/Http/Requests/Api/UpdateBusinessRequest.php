<?php

namespace App\Http\Requests\Api;

use App\Enums\PriceUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isBusinessOwner();
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:restaurant,touristic_agency,real_estate_agency,hotel'],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'wilaya' => ['nullable', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'detail' => ['sometimes', 'array'],
            'detail.average_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'detail.price_unit' => ['nullable', Rule::enum(PriceUnit::class)],
            'detail.number_of_rooms' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'detail.star_rating' => ['nullable', 'integer', 'between:1,5'],
            'detail.cuisine_type' => ['nullable', 'string', 'max:100'],
            'detail.seating_capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'detail.amenities' => ['nullable', 'array', 'max:50'],
            'detail.amenities.*' => ['string', 'max:100'],
            'detail.services' => ['nullable', 'array', 'max:50'],
            'detail.services.*' => ['string', 'max:100'],
        ];
    }
}

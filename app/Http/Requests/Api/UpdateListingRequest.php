<?php

namespace App\Http\Requests\Api;

use App\Enums\ListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isBusinessOwner();
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(ListingStatus::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isBusinessOwner();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

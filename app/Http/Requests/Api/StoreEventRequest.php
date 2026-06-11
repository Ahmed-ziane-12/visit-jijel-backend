<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isBusinessOwner() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'business_id' => ['nullable', 'exists:businesses,id'],
            'destination_id' => ['nullable', 'exists:destinations,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_attendees' => ['nullable', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,published,cancelled'],
        ];
    }
}

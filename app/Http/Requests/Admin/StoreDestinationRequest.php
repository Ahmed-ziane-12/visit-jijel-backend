<?php

namespace App\Http\Requests\Admin;

use App\Enums\DestinationCategory;
use App\Enums\DestinationState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'arabic_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'arabic_description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'arabic_address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'category' => ['required', Rule::enum(DestinationCategory::class)],
            'arabic_category' => ['nullable', 'string', 'max:100'],
            'is_featured' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'state' => ['sometimes', Rule::enum(DestinationState::class)],
        ];
    }
}

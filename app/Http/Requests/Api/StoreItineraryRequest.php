<?php

namespace App\Http\Requests\Api;

use App\Enums\ItineraryVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItineraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visibility' => ['sometimes', Rule::enum(ItineraryVisibility::class)],
        ];
    }
}

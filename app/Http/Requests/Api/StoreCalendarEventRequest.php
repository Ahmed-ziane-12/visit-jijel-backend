<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'itinerary_id' => ['nullable', 'exists:itineraries,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'all_day' => ['sometimes', 'boolean'],
            'source' => ['required', 'in:manual,itinerary,event'],
        ];
    }
}

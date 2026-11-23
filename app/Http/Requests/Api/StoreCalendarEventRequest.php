<?php

namespace App\Http\Requests\Api;

use App\Enums\CalendarEventSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'itinerary_id' => [
                'nullable',
                Rule::exists('itineraries', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'event_id' => ['nullable', 'exists:events,id'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'all_day' => ['sometimes', 'boolean'],
            'source' => ['required', Rule::enum(CalendarEventSource::class)],
        ];
    }
}

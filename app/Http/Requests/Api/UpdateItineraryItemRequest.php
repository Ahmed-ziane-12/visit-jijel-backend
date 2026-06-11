<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItineraryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'destination_id' => ['nullable', 'exists:destinations,id'],
            'listing_id' => ['nullable', 'exists:listings,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'item_type' => ['sometimes', 'in:destination,listing,event,custom'],
        ];
    }
}

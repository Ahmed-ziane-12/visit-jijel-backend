<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreItineraryItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'item_type' => ['required', 'in:destination,listing,event,custom'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('item_type');

            // Enforce that the FK matches the item_type
            if ($type === 'destination' && ! $this->input('destination_id')) {
                $validator->errors()->add('destination_id', 'A destination is required for this item type.');
            }
            if ($type === 'listing' && ! $this->input('listing_id')) {
                $validator->errors()->add('listing_id', 'A listing is required for this item type.');
            }
            if ($type === 'event' && ! $this->input('event_id')) {
                $validator->errors()->add('event_id', 'An event is required for this item type.');
            }
        });
    }
}

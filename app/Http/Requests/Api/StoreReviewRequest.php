<?php

namespace App\Http\Requests\Api;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['nullable', 'exists:listings,id'],
            'destination_id' => ['nullable', 'exists:destinations,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $filled = collect([
                $this->input('listing_id'),
                $this->input('destination_id'),
                $this->input('event_id'),
            ])->filter()->count();

            // Exactly one target must be set
            if ($filled !== 1) {
                $validator->errors()->add(
                    'target',
                    'A review must target exactly one of: listing, destination, or event.'
                );
            }

            // Prevent duplicate reviews on the same target
            if ($filled === 1) {
                $column = match (true) {
                    ! is_null($this->input('listing_id')) => ['listing_id',     $this->input('listing_id')],
                    ! is_null($this->input('destination_id')) => ['destination_id', $this->input('destination_id')],
                    default => ['event_id',       $this->input('event_id')],
                };

                $exists = Review::where('user_id', '=', $this->user()->id, 'and')
                    ->where($column[0], '=', $column[1], 'and')
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'target',
                        'You have already reviewed this.'
                    );
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItineraryDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'day_date' => ['sometimes', 'date'],
            'day_number' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Services\ItineraryGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['sometimes', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'vibes' => ['sometimes', 'array', 'max:4'],
            'vibes.*' => ['string', 'in:beach,mountain,food,history'],
            'preferences' => ['sometimes', 'array', 'max:6'],
            'preferences.*' => ['string', 'in:beaches,history,nature,food,adventure,city'],
            'accommodation' => ['sometimes', 'in:booked,notBooked'],
            'status' => ['sometimes', 'in:draft,published'],
            'budget' => ['sometimes', 'array'],
            'budget.budgetType' => ['required_with:budget', 'string', 'in:budget,standard,luxury,custom'],
            'budget.customBudget' => ['nullable', 'numeric', 'min:0'],
            'budget.customBudgetType' => ['nullable', 'in:daily,overall'],
        ];

        $startDate = $this->input('start_date');

        if (is_string($startDate) && $startDate !== '') {
            $rules['end_date'][] = 'before_or_equal:'.Carbon::parse($startDate)
                ->addDays(ItineraryGenerator::MAX_TRIP_DAYS - 1)
                ->toDateString();
        }

        return $rules;
    }
}

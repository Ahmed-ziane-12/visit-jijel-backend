<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'shareable_type' => ['nullable', 'string', 'in:destination,business,event'],
            'shareable_id' => ['required_with:shareable_type', 'integer', 'exists:destinations,id'],
            'parent_post_id' => ['nullable', 'integer', 'exists:posts,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (is_null($this->input('body')) && is_null($this->input('shareable_type')) && is_null($this->input('parent_post_id'))) {
                $validator->errors()->add(
                    'content',
                    'A post must have a body, a shared item, or be a re-share.'
                );
            }

            if ($this->input('shareable_type') && ! is_null($this->input('shareable_id'))) {
                $type = $this->input('shareable_type');
                $id = $this->input('shareable_id');
                $table = match ($type) {
                    'destination' => 'destinations',
                    'business' => 'businesses',
                    'event' => 'events',
                };

                if (! \DB::table($table)->where('id', $id)->exists()) {
                    $validator->errors()->add('shareable_id', 'The selected shared item does not exist.');
                }
            }
        });
    }
}

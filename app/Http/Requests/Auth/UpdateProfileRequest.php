<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['sometimes', 'string', 'max:20'],
            'bio' => ['sometimes', 'string', 'max:500'],
            'wilaya' => ['sometimes', 'string', 'max:100'],
            'commune' => ['sometimes', 'string', 'max:100'],
        ];
    }
}

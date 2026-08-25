<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'owner';
    }

    public function rules(): array
    {
        return ['is_available' => ['required', 'boolean']];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCafeSettingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role?->value === 'owner'; }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'thank_you_message' => ['nullable', 'string', 'max:255']];
    }
}

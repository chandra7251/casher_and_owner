<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCafeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'owner';
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'currency' => ['sometimes', 'string', 'size:3', 'uppercase', 'in:IDR,USD,SGD,MYR'], 'address' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'thank_you_message' => ['nullable', 'string', 'max:255'], 'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }
}

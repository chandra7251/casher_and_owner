<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role?->value === 'owner'; }
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80', Rule::unique('tables', 'name')->ignore($this->route('table'))], 'status' => ['required', Rule::in(['available', 'occupied'])]];
    }
}
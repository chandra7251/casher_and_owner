<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'owner';
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80', Rule::unique('menu_categories', 'name')->ignore($this->route('menuCategory'))], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:999']];
    }
}

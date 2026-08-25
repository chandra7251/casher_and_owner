<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'owner';
    }

    public function rules(): array
    {
        return ['menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'], 'name' => ['required', 'string', 'max:120'], 'is_available' => ['sometimes', 'boolean'], 'sizes' => ['required', 'array', 'size:2'], 'sizes.*.size' => ['required', 'distinct', Rule::in(['Regular', 'Large'])], 'sizes.*.price' => ['required', 'integer', 'min:0'], 'sizes.*.on_hand' => ['required', 'integer', 'min:0'], 'sizes.*.low_stock_threshold' => ['required', 'integer', 'min:0']];
    }
}

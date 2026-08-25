<?php

namespace App\Http\Requests;

class UpdateMenuItemRequest extends StoreMenuItemRequest
{
    public function rules(): array
    {
        return ['menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'], 'name' => ['required', 'string', 'max:120'], 'is_available' => ['sometimes', 'boolean']];
    }
}

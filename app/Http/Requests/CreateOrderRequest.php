<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['table_id' => ['required', 'integer', 'exists:tables,id'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'exists:menu_items,id'], 'items.*.size' => ['required', Rule::in(['Regular', 'Large'])], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99']];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuSizeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role?->value === 'owner'; }

    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'min:0'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ];
    }
}

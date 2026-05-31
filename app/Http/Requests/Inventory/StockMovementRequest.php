<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

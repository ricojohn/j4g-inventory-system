<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view inventory') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'action' => ['required', Rule::in(['stock-in', 'stock-out', 'reserve', 'release', 'damage', 'adjust'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.new_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

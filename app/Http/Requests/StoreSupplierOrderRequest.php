<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create supplier orders') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'from_order_id' => ['nullable', 'integer', 'exists:customer_orders,id'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_color_size_id' => ['required', 'integer', 'exists:product_color_sizes,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.customer_order_item_id' => ['nullable', 'integer', 'exists:customer_order_items,id'],
        ];
    }
}

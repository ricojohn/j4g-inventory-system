<?php

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create orders') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_source' => ['nullable', 'string', Rule::in(array_column(CustomerSource::cases(), 'value'))],
            'customer_notes' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'order_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_color_size_id' => ['required', 'integer', 'exists:product_color_sizes,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

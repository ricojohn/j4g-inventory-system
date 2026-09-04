<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessengerOrderDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create messenger orders') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'fulfillment_method' => ['required', Rule::in(['delivery', 'pickup'])],
            'delivery_address' => ['nullable', 'required_if:fulfillment_method,delivery', 'string', 'max:2000'],
            'payment_method_preference' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_color_size_id' => ['required', 'integer', 'distinct', 'exists:product_color_sizes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}

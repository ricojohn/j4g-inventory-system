<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create categories') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:product_categories,code'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}

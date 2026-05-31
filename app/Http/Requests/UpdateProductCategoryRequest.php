<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit categories') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('product_categories', 'code')->ignore($this->route('category'))],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}

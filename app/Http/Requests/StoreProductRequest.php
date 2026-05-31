<?php

namespace App\Http\Requests;

use App\Rules\SizesBelongToCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create products') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryId = $this->integer('product_category_id');

        return [
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'size_ids' => ['required', 'array', 'min:1'],
            'size_ids.*' => ['integer', 'exists:sizes,id', new SizesBelongToCategory($categoryId)],
        ];
    }
}

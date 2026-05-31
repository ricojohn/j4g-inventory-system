<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit products') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'color_name' => ['sometimes', 'required', 'string', 'max:100'],
            'color_code' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage sizes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:sizes,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

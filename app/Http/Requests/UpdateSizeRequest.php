<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSizeRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sizes', 'name')->ignore($this->route('size')),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreProductColorsRequest extends FormRequest
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
            'colors' => ['required', 'array', 'min:1'],
            'colors.*.color_name' => ['required', 'string', 'max:100'],
            'colors.*.color_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}

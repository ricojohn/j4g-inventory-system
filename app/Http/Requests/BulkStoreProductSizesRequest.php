<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreProductSizesRequest extends FormRequest
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
            'size_names' => ['required', 'array', 'min:1'],
            'size_names.*' => ['required', 'string', 'max:50'],
        ];
    }
}

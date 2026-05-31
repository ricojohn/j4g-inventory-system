<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncCategorySizesRequest extends FormRequest
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
            'size_ids' => ['present', 'array'],
            'size_ids.*' => ['integer', 'exists:sizes,id'],
        ];
    }
}

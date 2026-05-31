<?php

namespace App\Http\Requests\Admin;

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
        $sizeId = $this->route('size')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sizes', 'name')->ignore($sizeId),
            ],
        ];
    }
}

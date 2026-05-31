<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage colors') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $colorId = $this->route('color')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('colors', 'name')->ignore($colorId),
            ],
        ];
    }
}

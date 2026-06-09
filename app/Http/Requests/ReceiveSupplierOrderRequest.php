<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveSupplierOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receive supplier orders') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qtys' => ['required', 'array'],
            'qtys.*' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fulfill orders') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_method' => ['nullable', 'string', 'max:100'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'proof_or_tracking' => ['nullable', 'string', 'max:255'],
        ];
    }
}

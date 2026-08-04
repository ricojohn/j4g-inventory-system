<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseOrderDeliveryRequest extends FormRequest
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
            'release_override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

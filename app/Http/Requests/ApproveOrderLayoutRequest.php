<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOrderLayoutRequest extends FormRequest
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
            'approval_channel' => ['nullable', 'string', 'max:100'],
        ];
    }
}

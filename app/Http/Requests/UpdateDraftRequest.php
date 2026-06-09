<?php

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('use ai assistant') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_source' => ['nullable', 'string', Rule::in(array_column(CustomerSource::cases(), 'value'))],
            'customer_notes' => ['nullable', 'string'],
            'matched_json' => ['nullable', 'array'],
        ];
    }
}

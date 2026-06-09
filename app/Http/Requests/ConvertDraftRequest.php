<?php

namespace App\Http\Requests;

use App\Enums\CustomerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertDraftRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_source' => ['required', 'string', Rule::in(array_column(CustomerSource::cases(), 'value'))],
            'customer_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_color_size_id' => ['required', 'integer', 'exists:product_color_sizes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}

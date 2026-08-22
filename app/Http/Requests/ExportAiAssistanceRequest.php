<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportAiAssistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('use ai assistance') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'min:1', 'max:50000'],
            'rows' => ['sometimes', 'nullable', 'array', 'max:200'],
            'rows.*' => ['array'],
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}

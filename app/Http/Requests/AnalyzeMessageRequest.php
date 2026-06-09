<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeMessageRequest extends FormRequest
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
            'raw_message' => ['required', 'string', 'min:3'],
            'model' => ['nullable', 'string'],
        ];
    }
}

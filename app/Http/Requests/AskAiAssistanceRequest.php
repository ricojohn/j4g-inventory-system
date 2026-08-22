<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskAiAssistanceRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:3', 'max:4000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:8000'],
        ];
    }
}

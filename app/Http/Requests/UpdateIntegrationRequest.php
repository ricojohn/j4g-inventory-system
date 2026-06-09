<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage integrations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provider = $this->route('provider');
        $models = config("services.{$provider}.models", []);

        return [
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string', Rule::in($models)],
        ];
    }
}

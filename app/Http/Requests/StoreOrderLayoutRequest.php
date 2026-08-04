<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderLayoutRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'layout_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,ai,psd', 'max:10240'],
        ];
    }
}

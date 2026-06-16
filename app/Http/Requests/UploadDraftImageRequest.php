<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDraftImageRequest extends FormRequest
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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Please choose an image to upload.',
            'image.image' => 'The file must be a valid image.',
            'image.mimes' => 'The image must be a JPG, PNG, or WEBP file.',
            'image.max' => 'The image may not be larger than 2MB.',
        ];
    }
}

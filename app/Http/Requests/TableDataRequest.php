<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TableDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function baseRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50,100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function pageNumber(): int
    {
        return $this->integer('page', 1);
    }

    public function perPageCount(): int
    {
        return $this->integer('per_page', 20);
    }
}

<?php

namespace App\Rules;

use App\Models\ProductCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SizesBelongToCategory implements ValidationRule
{
    public function __construct(private ?int $categoryId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->categoryId || ! is_numeric($value)) {
            $fail('Selected size is not valid for this category.');

            return;
        }

        $exists = ProductCategory::query()
            ->whereKey($this->categoryId)
            ->whereHas('sizes', fn ($query) => $query->where('sizes.id', (int) $value))
            ->exists();

        if (! $exists) {
            $fail('Selected size is not valid for this category.');
        }
    }
}

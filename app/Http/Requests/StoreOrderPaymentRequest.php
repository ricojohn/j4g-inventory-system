<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage finance') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $balanceDue = $this->balanceDue();

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balanceDue],
            'method' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $balanceDue = $this->balanceDue();

        return [
            'amount.max' => 'The payment amount may not be greater than the balance due (₱'.number_format($balanceDue, 2).').',
            'amount.min' => 'The payment amount must be at least ₱0.01.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->balanceDue() <= 0) {
                $validator->errors()->add('amount', 'This order is already fully paid.');
            }
        });
    }

    private function balanceDue(): float
    {
        $order = $this->route('order');

        if (! $order instanceof CustomerOrder) {
            return 0.0;
        }

        return max(0, $order->balanceDue());
    }
}

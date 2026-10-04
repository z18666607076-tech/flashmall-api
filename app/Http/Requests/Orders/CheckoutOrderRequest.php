<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');

        $this->merge([
            'idempotency_key' => is_string($key) && $key !== '' ? $key : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}

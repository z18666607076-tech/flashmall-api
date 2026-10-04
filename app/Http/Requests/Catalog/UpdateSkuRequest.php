<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSkuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper((string) $this->input('currency')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attrs' => ['required', 'array'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests\FlashSales;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseFlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}

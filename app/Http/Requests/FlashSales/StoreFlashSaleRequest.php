<?php

namespace App\Http\Requests\FlashSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlashSaleRequest extends FormRequest
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
            'sku_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:120'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'total_stock' => ['required', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
        ];
    }
}

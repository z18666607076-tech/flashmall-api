<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

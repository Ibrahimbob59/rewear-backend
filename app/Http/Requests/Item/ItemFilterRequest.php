<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class ItemFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|in:tops,bottoms,dresses,outerwear,shoes,accessories,other',
            'size' => 'nullable|in:XS,S,M,L,XL,XXL,XXXL,One Size',
            'condition' => 'nullable|in:new,like_new,good,fair',
            'gender' => 'nullable|in:male,female,unisex',
            'is_donation' => 'nullable|boolean',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'sort_by' => 'nullable|in:newest,oldest,price_low,price_high',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Invalid category',
            'size.in' => 'Invalid size',
            'condition.in' => 'Invalid condition',
            'gender.in' => 'Invalid gender',
            'is_donation.boolean' => 'Invalid donation filter',
            'min_price.numeric' => 'Minimum price must be a number',
            'max_price.numeric' => 'Maximum price must be a number',
            'sort_by.in' => 'Invalid sort option',
            'per_page.integer' => 'Per page must be a number',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 50',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate price range
            if ($this->filled('min_price') && $this->filled('max_price')) {
                if ($this->input('min_price') > $this->input('max_price')) {
                    $validator->errors()->add('min_price', 'Minimum price cannot be greater than maximum price');
                }
            }
        });
    }
}

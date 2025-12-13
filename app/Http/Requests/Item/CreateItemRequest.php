<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category' => 'required|in:tops,bottoms,dresses,outerwear,shoes,accessories,other',
            'size' => 'required|in:XS,S,M,L,XL,XXL,XXXL,One Size',
            'condition' => 'required|in:new,like_new,good,fair',
            'gender' => 'nullable|in:male,female,unisex',
            'brand' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0.01|max:999999.99',
            'is_donation' => 'required|boolean',
            'images' => 'required|array|min:1|max:6',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Item title is required',
            'description.required' => 'Item description is required',
            'category.required' => 'Category is required',
            'category.in' => 'Invalid category selected',
            'size.required' => 'Size is required',
            'size.in' => 'Invalid size selected',
            'condition.required' => 'Condition is required',
            'condition.in' => 'Invalid condition selected',
            'gender.in' => 'Invalid gender selected',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price must be at least $0.01',
            'price.max' => 'Price cannot exceed $999,999.99',
            'is_donation.required' => 'Please specify if this is a donation',
            'is_donation.boolean' => 'Invalid donation value',
            'images.required' => 'At least one image is required',
            'images.min' => 'At least one image is required',
            'images.max' => 'Maximum 6 images allowed',
            'images.*.image' => 'Each file must be an image',
            'images.*.mimes' => 'Images must be jpeg, png, jpg, or webp format',
            'images.*.max' => 'Each image must not exceed 5MB',
        ];
    }

    protected function prepareForValidation(): void
    {
        // If is_donation is true, remove price validation
        if ($this->boolean('is_donation')) {
            $this->merge(['price' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If not donation, price is required
            if (!$this->boolean('is_donation') && empty($this->input('price'))) {
                $validator->errors()->add('price', 'Price is required for items that are not donations');
            }

            // If donation, price must be null
            if ($this->boolean('is_donation') && !empty($this->input('price'))) {
                $validator->errors()->add('price', 'Price must not be set for donations');
            }
        });
    }
}

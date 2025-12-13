<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'category' => 'sometimes|in:tops,bottoms,dresses,outerwear,shoes,accessories,other',
            'size' => 'sometimes|in:XS,S,M,L,XL,XXL,XXXL,One Size',
            'condition' => 'sometimes|in:new,like_new,good,fair',
            'gender' => 'nullable|in:male,female,unisex',
            'brand' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0.01|max:999999.99',
            'is_donation' => 'sometimes|boolean',
            'images' => 'sometimes|array|max:6',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'title.string' => 'Title must be text',
            'description.string' => 'Description must be text',
            'category.in' => 'Invalid category selected',
            'size.in' => 'Invalid size selected',
            'condition.in' => 'Invalid condition selected',
            'gender.in' => 'Invalid gender selected',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price must be at least $0.01',
            'price.max' => 'Price cannot exceed $999,999.99',
            'is_donation.boolean' => 'Invalid donation value',
            'images.max' => 'Maximum 6 images allowed',
            'images.*.image' => 'Each file must be an image',
            'images.*.mimes' => 'Images must be jpeg, png, jpg, or webp format',
            'images.*.max' => 'Each image must not exceed 5MB',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If changing to donation, price should be null
            if ($this->boolean('is_donation') && !empty($this->input('price'))) {
                $validator->errors()->add('price', 'Price must not be set for donations');
            }
        });
    }
}

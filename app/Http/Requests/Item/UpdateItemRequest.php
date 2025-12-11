<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in controller (item ownership check)
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'min:10'],
            'category' => ['sometimes', 'required', 'string', 'in:tops,bottoms,dresses,outerwear,shoes,accessories,other'],
            'size' => ['sometimes', 'required', 'string', 'in:XS,S,M,L,XL,XXL,XXXL,One Size'],
            'condition' => ['sometimes', 'required', 'string', 'in:new,like_new,good,fair'],
            'gender' => ['nullable', 'string', 'in:male,female,unisex'],
            'brand' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'is_donation' => ['sometimes', 'boolean'],
            'images' => ['nullable', 'array', 'max:6'], // New images (optional)
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Item title is required',
            'title.max' => 'Item title cannot exceed 255 characters',
            'description.required' => 'Item description is required',
            'description.min' => 'Description must be at least 10 characters',
            'category.in' => 'Invalid category selected',
            'size.in' => 'Invalid size selected',
            'condition.in' => 'Invalid condition selected',
            'gender.in' => 'Invalid gender selected',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price cannot be negative',
            'price.max' => 'Price cannot exceed $99,999.99',
            'is_donation.boolean' => 'Invalid donation flag',
            'images.max' => 'Maximum 6 new images allowed',
            'images.*.image' => 'File must be an image',
            'images.*.mimes' => 'Image must be jpeg, jpg, png, or webp',
            'images.*.max' => 'Image size cannot exceed 5MB',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert is_donation to boolean if present
        if ($this->has('is_donation')) {
            $this->merge([
                'is_donation' => filter_var($this->is_donation, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // If changing to donation, set price to null
        if ($this->has('is_donation') && $this->is_donation) {
            $this->merge(['price' => null]);
        }
    }
}
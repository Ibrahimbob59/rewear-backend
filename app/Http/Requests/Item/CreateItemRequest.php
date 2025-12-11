<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'category' => ['required', 'string', 'in:tops,bottoms,dresses,outerwear,shoes,accessories,other'],
            'size' => ['required', 'string', 'in:XS,S,M,L,XL,XXL,XXXL,One Size'],
            'condition' => ['required', 'string', 'in:new,like_new,good,fair'],
            'gender' => ['nullable', 'string', 'in:male,female,unisex'],
            'brand' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'is_donation' => ['required', 'boolean'],
            'images' => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5MB
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
            'category.required' => 'Please select a category',
            'category.in' => 'Invalid category selected',
            'size.required' => 'Please select a size',
            'size.in' => 'Invalid size selected',
            'condition.required' => 'Please select item condition',
            'condition.in' => 'Invalid condition selected',
            'gender.in' => 'Invalid gender selected',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price cannot be negative',
            'price.max' => 'Price cannot exceed $99,999.99',
            'is_donation.required' => 'Please specify if this is a donation',
            'is_donation.boolean' => 'Invalid donation flag',
            'images.required' => 'Please upload at least 1 image',
            'images.min' => 'Please upload at least 1 image',
            'images.max' => 'Maximum 6 images allowed',
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
        // Convert is_donation to boolean
        if ($this->has('is_donation')) {
            $this->merge([
                'is_donation' => filter_var($this->is_donation, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // If donation, set price to null
        if ($this->is_donation) {
            $this->merge(['price' => null]);
        }

        // Set default gender if not provided
        if (!$this->has('gender')) {
            $this->merge(['gender' => 'unisex']);
        }
    }

    /**
     * Get validated data with defaults
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure gender has a default
        if (!isset($validated['gender'])) {
            $validated['gender'] = 'unisex';
        }

        return $validated;
    }
}
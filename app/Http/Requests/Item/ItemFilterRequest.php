<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class ItemFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:tops,bottoms,dresses,outerwear,shoes,accessories,other'],
            'size' => ['nullable', 'string', 'in:XS,S,M,L,XL,XXL,XXXL,One Size'],
            'condition' => ['nullable', 'string', 'in:new,like_new,good,fair'],
            'gender' => ['nullable', 'string', 'in:male,female,unisex'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'is_donation' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:newest,oldest,price_low,price_high,distance'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            
            // Location filters
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:1', 'max:200'], // km
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Invalid category',
            'size.in' => 'Invalid size',
            'condition.in' => 'Invalid condition',
            'gender.in' => 'Invalid gender',
            'min_price.numeric' => 'Minimum price must be a number',
            'min_price.min' => 'Minimum price cannot be negative',
            'max_price.numeric' => 'Maximum price must be a number',
            'max_price.min' => 'Maximum price cannot be negative',
            'max_price.gte' => 'Maximum price must be greater than or equal to minimum price',
            'is_donation.boolean' => 'Invalid donation filter',
            'sort.in' => 'Invalid sort option',
            'page.integer' => 'Page must be an integer',
            'page.min' => 'Page must be at least 1',
            'per_page.integer' => 'Items per page must be an integer',
            'per_page.min' => 'Items per page must be at least 1',
            'per_page.max' => 'Items per page cannot exceed 100',
            'latitude.numeric' => 'Latitude must be a number',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.numeric' => 'Longitude must be a number',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'radius.numeric' => 'Radius must be a number',
            'radius.min' => 'Radius must be at least 1km',
            'radius.max' => 'Radius cannot exceed 200km',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean strings
        if ($this->has('is_donation')) {
            $this->merge([
                'is_donation' => filter_var($this->is_donation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        // Set defaults
        $defaults = [
            'sort' => 'newest',
            'page' => 1,
            'per_page' => 20,
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }

    /**
     * Get validated filters
     */
    public function getFilters(): array
    {
        return [
            'search' => $this->input('search'),
            'category' => $this->input('category'),
            'size' => $this->input('size'),
            'condition' => $this->input('condition'),
            'gender' => $this->input('gender'),
            'min_price' => $this->input('min_price'),
            'max_price' => $this->input('max_price'),
            'is_donation' => $this->input('is_donation'),
            'sort' => $this->input('sort', 'newest'),
            'latitude' => $this->input('latitude'),
            'longitude' => $this->input('longitude'),
            'radius' => $this->input('radius', 50), // Default 50km
        ];
    }

    /**
     * Get pagination settings
     */
    public function getPagination(): int
    {
        return (int) $this->input('per_page', 20);
    }
}
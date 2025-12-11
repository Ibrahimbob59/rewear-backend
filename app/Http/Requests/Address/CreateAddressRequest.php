<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class CreateAddressRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:50'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['required', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'label.max' => 'Address label cannot exceed 50 characters',
            'full_name.max' => 'Full name cannot exceed 255 characters',
            'phone.max' => 'Phone number cannot exceed 20 characters',
            'address_line1.required' => 'Address line 1 is required',
            'address_line1.max' => 'Address line 1 cannot exceed 500 characters',
            'address_line2.max' => 'Address line 2 cannot exceed 500 characters',
            'city.required' => 'City is required',
            'city.max' => 'City name cannot exceed 100 characters',
            'state.max' => 'State name cannot exceed 100 characters',
            'postal_code.max' => 'Postal code cannot exceed 20 characters',
            'country.required' => 'Country is required',
            'country.max' => 'Country name cannot exceed 100 characters',
            'latitude.numeric' => 'Latitude must be a number',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.numeric' => 'Longitude must be a number',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'is_default.boolean' => 'Invalid default flag',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean
        if ($this->has('is_default')) {
            $this->merge([
                'is_default' => filter_var($this->is_default, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Set default country if not provided
        if (!$this->has('country')) {
            $this->merge(['country' => 'Lebanon']);
        }
    }
}

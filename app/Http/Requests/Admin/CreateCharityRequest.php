<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateCharityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admin users can create charity accounts
        // For now, we'll implement a simple check
        // In production, you might want to add a proper role/permission system
        return $this->user() && $this->user()->user_type === 'user' && $this->user()->id === 1;
        // TODO: Implement proper admin role checking
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()],
            'city' => ['nullable', 'string', 'max:100'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'organization_name.required' => 'Organization name is required',
            'organization_name.min' => 'Organization name must be at least 3 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'phone.required' => 'Phone number is required',
            'phone.regex' => 'Please provide a valid phone number (with country code)',
            'password.min' => 'Password must be at least 8 characters',
            'location_lat.between' => 'Latitude must be between -90 and 90',
            'location_lng.between' => 'Longitude must be between -180 and 180',
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', // At least one lowercase, uppercase, and digit
            ],
            'full_name' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^\+?[1-9]\d{7,14}$/', // International phone format
                'unique:users,phone',
            ],
            'code' => [
                'required',
                'string',
                'size:6',
                'regex:/^\d{6}$/', // Exactly 6 digits
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'email.max' => 'Email address must not exceed 255 characters.',

            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',

            'full_name.required' => 'Full name is required.',
            'full_name.min' => 'Full name must be at least 2 characters long.',
            'full_name.max' => 'Full name must not exceed 255 characters.',

            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please provide a valid phone number (e.g., +96170123456).',
            'phone.unique' => 'This phone number is already registered.',

            'code.required' => 'Verification code is required.',
            'code.size' => 'Verification code must be exactly 6 digits.',
            'code.regex' => 'Verification code must contain only numbers.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'code' => 'verification code',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

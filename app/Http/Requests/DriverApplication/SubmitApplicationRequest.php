<?php

namespace App\Http\Requests\DriverApplication;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
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
            'full_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\-\.\']+$/', // Only letters, spaces, hyphens, dots, apostrophes
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/', // Phone format validation
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'address' => [
                'required',
                'string',
                'max:255',
            ],
            'city' => [
                'required',
                'string',
                'max:100',
            ],
            'vehicle_type' => [
                'required',
                'string',
                'in:car,motorcycle,bicycle',
            ],
            'id_document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB
            ],
            'driving_license' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB
            ],
            'vehicle_registration' => [
                'nullable',
                'required_unless:vehicle_type,bicycle',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required',
            'full_name.regex' => 'Full name can only contain letters, spaces, hyphens, dots, and apostrophes',
            'phone.required' => 'Phone number is required',
            'phone.regex' => 'Please enter a valid phone number',
            'address.required' => 'Address is required',
            'city.required' => 'City is required',
            'vehicle_type.required' => 'Vehicle type is required',
            'vehicle_type.in' => 'Vehicle type must be car, motorcycle, or bicycle',
            'id_document.required' => 'ID document is required',
            'id_document.mimes' => 'ID document must be a JPG, PNG, or PDF file',
            'id_document.max' => 'ID document must not exceed 5MB',
            'driving_license.required' => 'Driving license is required',
            'driving_license.mimes' => 'Driving license must be a JPG, PNG, or PDF file',
            'driving_license.max' => 'Driving license must not exceed 5MB',
            'vehicle_registration.required_unless' => 'Vehicle registration is required for cars and motorcycles',
            'vehicle_registration.mimes' => 'Vehicle registration must be a JPG, PNG, or PDF file',
            'vehicle_registration.max' => 'Vehicle registration must not exceed 5MB',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'phone' => 'phone number',
            'email' => 'email address',
            'address' => 'address',
            'city' => 'city',
            'vehicle_type' => 'vehicle type',
            'id_document' => 'ID document',
            'driving_license' => 'driving license',
            'vehicle_registration' => 'vehicle registration',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional validation: Check file sizes collectively
            $totalSize = 0;
            foreach (['id_document', 'driving_license', 'vehicle_registration'] as $file) {
                if ($this->hasFile($file)) {
                    $totalSize += $this->file($file)->getSize();
                }
            }

            // Total files should not exceed 15MB
            if ($totalSize > 15 * 1024 * 1024) {
                $validator->errors()->add('documents', 'Total file size should not exceed 15MB');
            }

            // Validate phone number format more strictly
            $phone = $this->input('phone');
            if ($phone && !preg_match('/^\+?[\d\s\-\(\)]{8,20}$/', $phone)) {
                $validator->errors()->add('phone', 'Please enter a valid phone number (8-20 digits)');
            }
        });
    }
}

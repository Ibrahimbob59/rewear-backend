<?php

namespace App\Http\Requests\Charity;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Address;

class AcceptDonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('charity');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'delivery_address_id' => [
                'required',
                'integer',
                'exists:addresses,id',
                function ($attribute, $value, $fail) {
                    // Check if address belongs to the authenticated user
                    $address = Address::find($value);
                    if ($address && $address->user_id !== auth()->id()) {
                        $fail('The selected delivery address does not belong to you.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'delivery_address_id.required' => 'Delivery address is required',
            'delivery_address_id.integer' => 'Delivery address ID must be a valid number',
            'delivery_address_id.exists' => 'The selected delivery address does not exist',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'delivery_address_id' => 'delivery address',
        ];
    }
}

<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'delivery_address_id' => ['required', 'integer', 'exists:addresses,id'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'distance_km' => ['nullable', 'numeric', 'min:0'], // For reference/validation
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'item_id.required' => 'Item is required',
            'item_id.integer' => 'Invalid item ID',
            'item_id.exists' => 'Item not found',
            'delivery_address_id.required' => 'Delivery address is required',
            'delivery_address_id.integer' => 'Invalid address ID',
            'delivery_address_id.exists' => 'Delivery address not found',
            'delivery_fee.required' => 'Delivery fee is required',
            'delivery_fee.numeric' => 'Delivery fee must be a number',
            'delivery_fee.min' => 'Delivery fee cannot be negative',
            'delivery_fee.max' => 'Delivery fee cannot exceed $9,999.99',
            'distance_km.numeric' => 'Distance must be a number',
            'distance_km.min' => 'Distance cannot be negative',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate delivery fee calculation if distance provided
            if ($this->has('distance_km') && $this->has('delivery_fee')) {
                $expectedFee = round(($this->distance_km / 4) * 1, 2);
                $providedFee = round($this->delivery_fee, 2);
                
                // Allow 0.10 cent tolerance for rounding
                if (abs($expectedFee - $providedFee) > 0.10) {
                    $validator->errors()->add(
                        'delivery_fee',
                        "Delivery fee calculation mismatch. Expected: ${expectedFee} for {$this->distance_km}km"
                    );
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|integer|exists:items,id',
            'delivery_address_id' => 'required|integer|exists:addresses,id',
            'delivery_fee' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'Item ID is required',
            'item_id.exists' => 'Selected item does not exist',
            'delivery_address_id.required' => 'Delivery address is required',
            'delivery_address_id.exists' => 'Selected address does not exist',
            'delivery_fee.required' => 'Delivery fee is required',
            'delivery_fee.numeric' => 'Delivery fee must be a valid number',
            'delivery_fee.min' => 'Delivery fee cannot be negative',
        ];
    }
}

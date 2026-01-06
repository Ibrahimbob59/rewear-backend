<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use App\Models\DriverApplication;

class AssignDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'driver_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Check if user is approved driver
                        $isApprovedDriver = DriverApplication::where('user_id', $value)
                            ->where('status', 'approved')
                            ->exists();

                        if (!$isApprovedDriver) {
                            $fail('The selected user is not an approved driver.');
                        }

                        // Check if driver has too many active deliveries
                        $user = User::find($value);
                        if ($user && $user->activeDeliveries()->count() >= 3) {
                            $fail('This driver has reached the maximum number of active deliveries.');
                        }
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
            'driver_id.integer' => 'Driver ID must be a valid number',
            'driver_id.exists' => 'The selected driver does not exist',
        ];
    }
}

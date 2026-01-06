<?php

namespace App\Http\Requests\Charity;

use Illuminate\Foundation\Http\FormRequest;

class MarkDistributedRequest extends FormRequest
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
            'people_helped' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'distribution_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'people_helped.required' => 'Number of people helped is required',
            'people_helped.integer' => 'Number of people helped must be a valid number',
            'people_helped.min' => 'You must have helped at least 1 person',
            'people_helped.max' => 'Number of people helped cannot exceed 1000',
            'distribution_notes.string' => 'Distribution notes must be valid text',
            'distribution_notes.max' => 'Distribution notes cannot exceed 1000 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'people_helped' => 'number of people helped',
            'distribution_notes' => 'distribution notes',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional validation: Check if distribution notes are provided for large numbers
            $peopleHelped = $this->input('people_helped');
            $notes = $this->input('distribution_notes');

            if ($peopleHelped > 50 && empty($notes)) {
                $validator->errors()->add(
                    'distribution_notes',
                    'Distribution notes are required when helping more than 50 people.'
                );
            }
        });
    }
}

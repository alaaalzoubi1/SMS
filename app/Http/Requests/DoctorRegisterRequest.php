<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DoctorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Allow access
    }

    public function rules(): array
    {
        return [
            // Account Info
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:accounts,email',
            'password'      => 'required|string|min:8|confirmed',
            'phone_number'  => 'required|string|unique:accounts,phone_number',

            // Doctor Info
            'specialization' => 'required|string|max:255',
            'address'        => 'required|string|max:255',
            'age'            => 'required|integer|min:21|max:99',
            'gender'         => 'required|in:male,female',
            'instructions_before_booking' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already in use.',
            'gender.in' => 'Gender must be male, female.',
        ];
    }
}

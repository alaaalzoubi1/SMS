<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NurseRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Allow access for now, you can modify this based on your authorization logic
    }

    public function rules(): array
    {
        return [
            // Account Info (Email, Password, Phone Number)
            'email' => 'required|email|unique:accounts,email',
            'password' => 'required|string|min:8|confirmed',  // Confirm password rule
            'phone_number' => 'required|string|unique:accounts,phone_number',

            // Nurse Info (Full Name, Address, Graduation Type, etc.)
            'full_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255', // Address is optional
            'graduation_type' => 'required|in:معهد,مدرسة,جامعة,ماجستير,دكتوراه',
            'longitude' => 'nullable|numeric',  // Longitude can be null
            'latitude' => 'nullable|numeric',   // Latitude can be null
            'age' => 'required|integer|min:21|max:99',
            'gender' => 'required|in:male,female',

            // Optional Description
            'profile_description' => 'nullable|string|max:500', // Profile description is optional

            // License Image (Required)
            'license_image' => 'required|image|mimes:jpg,jpeg,png,gif,pdf|max:2048',  // 2MB limit for license image
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already in use.',
            'phone_number.unique' => 'This phone number is already in use.',
            'password.confirmed' => 'The password confirmation does not match.',
            'graduation_type.in' => 'The graduation type must be one of the following: معهد, مدرسة, جامعة, ماجستير, دكتوراه.',
            'license_image.required' => 'A license image is required.',
            'license_image.image' => 'The license image must be a valid image.',
            'license_image.mimes' => 'The license image must be of type: jpg, jpeg, png, or gif.',
            'license_image.max' => 'The license image cannot be larger than 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'Full Name',
            'address' => 'Address',
            'graduation_type' => 'Graduation Type',
            'longitude' => 'Longitude',
            'latitude' => 'Latitude',
            'age' => 'Age',
            'gender' => 'Gender',
            'profile_description' => 'Profile Description',
            'license_image' => 'License Image',
        ];
    }
}

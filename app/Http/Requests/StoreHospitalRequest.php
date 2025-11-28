<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHospitalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hospital_name' => 'required|string|max:255|exists:hospitals,full_name',
            'unique_code'   => 'required|string|exists:hospitals,unique_code',
            'address'       => 'required|string|max:255',
            'email'          => 'required|email|unique:accounts,email' ,
            'phone_number'  => 'required|string|unique:accounts,phone_number',
            'password'     => 'required|string|min:8|confirmed',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'hospital_name.required' => 'The hospital name is required.',
            'unique_code.required'   => 'The unique code is required.',
            'address.required'       => 'The address is required.',
            'email.required'         => 'The email is required.',
            'phone_number.required'  => 'The phone number is required.',
        ];
    }
}

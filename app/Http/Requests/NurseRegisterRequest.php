<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NurseRegisterRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Account Info
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:accounts,email',
            'password'      => 'required|string|min:8|confirmed',
            'phone_number'  => 'required|string|unique:accounts,phone_number',

            // Nurse Info
            'specialization' => 'required|string|max:255',
            'study_stage'    => 'required|string|max:255',
            'age'            => 'required|integer|min:18|max:100',
            'gender'         => 'required|in:male,female',
            'longitude'      => 'required|numeric|between:-180,180',
            'latitude'       => 'required|numeric|between:-90,90',
        ];
    }
}

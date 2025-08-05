<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRegisterRequest extends FormRequest
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
            'email' => 'required|email|unique:accounts,email',
            'password' => 'required|confirmed|min:8',
            'phone_number' => 'required|unique:accounts,phone_number',
            'full_name' => 'required|string|max:50',
            'age' => 'required|integer|min:0',
            'gender' => 'required|in:male,female',
        ];
    }

}

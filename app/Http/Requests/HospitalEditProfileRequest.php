<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HospitalEditProfileRequest extends FormRequest
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
            'full_name'    => 'sometimes|required|string|max:255',
            'phone_number' => 'sometimes|required|string|unique:accounts,phone_number,',
            'address'      => 'sometimes|required|string|max:255',
            'profile_image' => 'sometimes|nullable|image|mimes:jpeg,jpg,png|max:10240',
            'province_id' => 'sometimes|required|integer|exists:provinces,id',
            'reservation_confirmation_deadline' => 'sometimes|required|numeric',
            'profile_description' => 'nullable|string',
            'hospital_type_id' => ['sometimes', 'required', 'integer', Rule::exists('hospital_types', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'hospital_type_id.required' => 'يجب اختيار نوع المشفى.',
            'hospital_type_id.exists'   => 'نوع المشفى المختار غير موجود.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Every hospital must choose its type from GET api/hospital-types.
            'hospital_type_id' => ['required', 'integer', Rule::exists('hospital_types', 'id')->whereNull('deleted_at')],
            'unique_code'   => 'required|string|exists:hospitals,unique_code',
            'address'       => 'required|string|max:255',
            'email'          => 'required|email|unique:accounts,email' ,
            'phone_number'  => 'required|string|unique:accounts,phone_number',
            'password'     => 'required|string|min:8|confirmed',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'province_id' => 'required|integer|exists:provinces,id',
            'reservation_confirmation_deadline' => 'required|numeric',
            'profile_description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'hospital_name.required'         => 'اسم المشفى مطلوب.',
            'hospital_name.exists'           => 'اسم المشفى غير موجود.',
            'hospital_type_id.required'      => 'يجب اختيار نوع المشفى.',
            'hospital_type_id.exists'        => 'نوع المشفى المختار غير موجود.',
            'unique_code.required'   => 'الرمز الخاص بالمشفى مطلوب.',
            'address.required'       => 'العنوان مطلوب.',
            'email.required'         => 'البريد الإلكتروني مطلوب.',
            'phone_number.required'  => 'رقم الهاتف مطلوب.',
        ];
    }
}

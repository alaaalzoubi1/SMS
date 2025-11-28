<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Enums\SpecializationType;

class DoctorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }



    public function rules(): array
    {
        return [
            // بيانات الحساب
            'full_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:accounts,email',
            'password'     => 'required|string|min:8|confirmed',
            'phone_number' => 'required|string|unique:accounts,phone_number',

            'specialization_id' => [
                'required',
                'integer',
                Rule::exists('specializations', 'id')->whereNull('deleted_at'),  // Exclude soft-deleted records
            ],
            'address'        => 'required|string|max:255',
            'age'            => 'required|integer|min:21|max:99',
            'gender'         => 'required|in:male,female',
            'profile_description' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',

            'license_image'  => 'required|image|mimes:jpeg,png,jpg,pdf|max:2048',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',


        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'phone_number.unique'=> 'رقم الهاتف مستخدم بالفعل.',
            'gender.in'          => 'يجب أن يكون الجنس "male" أو "female".',
            'specialization.in'  => 'اختصاص الطبيب غير صالح.',
            'specialization_id.exists' => 'الاختصاص المختار غير موجود أو تم حذفه.',
            'license_image.image'=> 'يجب أن تكون صورة صالحة.',
            'license_image.mimes'=> 'يجب أن يكون امتداد الصورة pdf أو jpeg أو png أو jpg.',
            'license_image.max'  => 'حجم الصورة لا يجب أن يتجاوز 10 ميجابايت.',
            'profile_image.image' => 'يجب أن تكون صورة صالحة.',
            'profile_image.mimes' => 'يجب أن يكون امتداد الصورة jpeg أو png أو jpg.',
            'profile_image.max'   => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت.',
        ];

    }
}

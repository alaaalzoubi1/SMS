<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use App\Enums\SpecializationType;

class DoctorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('specialization')) {
            $enumCase = SpecializationType::tryFromArabic($this->input('specialization'));
            if ($enumCase === null) {
                throw ValidationException::withMessages([
                    'specialization' => 'اختصاص الطبيب غير صالح.',
                ]);
            }

            $this->merge([
                'specialization' => $enumCase->value(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // بيانات الحساب
            'full_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:accounts,email',
            'password'     => 'required|string|min:8|confirmed',
            'phone_number' => 'required|string|unique:accounts,phone_number',

            // بيانات الطبيب
            'specialization' => 'required|integer|in:' . implode(',', array_map(fn($e) => $e->value(), SpecializationType::cases())),
            'address'        => 'required|string|max:255',
            'age'            => 'required|integer|min:21|max:99',
            'gender'         => 'required|in:male,female',

            // صورة الرخصة (اختيارية)
            'license_image'  => 'required|image|mimes:jpeg,png,jpg,pdf|max:2048',

        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'phone_number.unique'=> 'رقم الهاتف مستخدم بالفعل.',
            'gender.in'          => 'يجب أن يكون الجنس "male" أو "female".',
            'specialization.in'  => 'اختصاص الطبيب غير صالح.',
            'license_image.image'=> 'يجب أن تكون صورة صالحة.',
            'license_image.mimes'=> 'يجب أن يكون امتداد الصورة pdf أو  jpeg أو png أو jpg.',
            'license_image.max'  => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت.',
        ];
    }
}

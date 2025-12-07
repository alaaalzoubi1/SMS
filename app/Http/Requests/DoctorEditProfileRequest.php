<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorEditProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // تأكد أنك تحمي الروت بالـ auth و policies حسب الحاجة
    }

    public function rules(): array
    {
        return [
            'full_name'    => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:accounts,phone_number,' . auth()->id(),
            'address'      => 'sometimes|string|max:255',
            'age'          => 'sometimes|integer|min:21|max:99',
            'gender'       => 'sometimes|in:male,female',
            'specialization_id' => [
                'sometimes',
                'integer',
                Rule::exists('specializations', 'id')->whereNull('deleted_at'),
            ],
            'profile_description' => 'sometimes|string|max:1000',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg|max:10240',
            'province_id' => 'sometimes|integer|exists:provinces,id'
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.unique' => 'رقم الهاتف مستخدم من قبل.',
            'gender.in' => 'القيمة المدخلة للجنس غير صحيحة.',
            'profile_image.image' => 'يجب أن تكون صورة صالحة.',
            'profile_image.mimes' => 'يجب أن تكون الصورة jpeg أو png أو jpg.',
            'profile_image.max'   => 'حجم الصورة لا يجب أن يتجاوز 10 ميجابايت.',
        ];
    }
}

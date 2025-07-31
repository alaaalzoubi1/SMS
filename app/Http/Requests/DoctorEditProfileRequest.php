<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'specialization' => 'sometimes|string|max:255',
            'profile_description' => 'sometimes|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.unique' => 'رقم الهاتف مستخدم من قبل.',
            'gender.in' => 'القيمة المدخلة للجنس غير صحيحة.',
        ];
    }
}

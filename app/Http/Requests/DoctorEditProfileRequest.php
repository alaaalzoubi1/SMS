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
            'full_name'    => 'sometimes|required|string|max:255',
            'phone_number' => 'sometimes|required|string|unique:accounts,phone_number,' . auth()->id(),
            'address'      => 'sometimes|required|string|max:255',
            'age'          => 'sometimes|required|integer|min:21|max:99',
            'gender'       => 'sometimes|required|in:male,female',
            'specialization' => 'sometimes|required|string|max:255',
            'profile_description' => 'sometimes|nullable|string|max:1000',
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

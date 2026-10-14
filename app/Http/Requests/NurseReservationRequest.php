<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NurseReservationRequest extends FormRequest
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
            'nurse_id' => ['required', 'exists:nurses,id'],
            'nurse_service_ids' => ['required', 'array', 'min:1'],
            'nurse_service_ids.*' => [
                'integer',
                'distinct:strict',
                Rule::exists('nurse_services', 'id')->where('nurse_id', $this->nurse_id),
            ],
                'reservation_type' => ['required', Rule::in(['direct', 'manual'])],
            'start_at' => [
                Rule::requiredIf(fn() => $this->reservation_type === 'manual'),
                'nullable',
                'date',
                // A requested date can never be in the past.
                'after_or_equal:today',
            ],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'note' => ['nullable', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'confirm' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'start_at.required'       => 'يجب تحديد موعد الحجز عند اختيار حجز بموعد محدد.',
            'start_at.after_or_equal' => 'لا يمكن أن يكون موعد الحجز في الماضي.',
            'end_at.after_or_equal'   => 'وقت النهاية يجب أن يكون بعد وقت البداية.',
            'nurse_service_ids.required' => 'يجب اختيار خدمة واحدة على الأقل.',
            'lat.required'            => 'يجب تحديد الموقع.',
            'lng.required'            => 'يجب تحديد الموقع.',
        ];
    }

}

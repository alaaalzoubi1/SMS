<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorWorkScheduleRequest extends FormRequest
{
    /**
     * The doctor model must exist before we can scope the uniqueness check.
     */
    public function authorize(): bool
    {
        return auth()->user()?->doctor !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => [
                'required',
                'string',
                'in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
                Rule::unique('doctor_work_schedules')->where(function ($query) {
                    return $query->where('doctor_id', auth()->user()->doctor->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'day_of_week.in'          => 'اليوم المختار غير صحيح.',
            'day_of_week.unique'      => 'لديك دوام مسجل في هذا اليوم بالفعل.',
            'start_time.date_format'  => 'صيغة وقت البداية يجب أن تكون HH:MM.',
            'end_time.date_format'    => 'صيغة وقت النهاية يجب أن تكون HH:MM.',
        ];
    }

    public function attributes(): array
    {
        return [
            'day_of_week' => 'اليوم',
            'start_time'  => 'وقت البداية',
            'end_time'    => 'وقت النهاية',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('day_of_week') && is_string($this->day_of_week)) {
            $this->merge([
                'day_of_week' => strtolower(trim($this->day_of_week)),
            ]);
        }
    }
}

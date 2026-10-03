<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDoctorWorkScheduleRequest extends FormRequest
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
            'day_of_week' => 'sometimes|string|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'start_time'  => 'sometimes|date_format:H:i',
            'end_time'    => 'sometimes|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'day_of_week.in'         => 'اليوم المختار غير صحيح.',
            'start_time.date_format' => 'صيغة وقت البداية يجب أن تكون HH:MM.',
            'end_time.date_format'   => 'صيغة وقت النهاية يجب أن تكون HH:MM.',
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

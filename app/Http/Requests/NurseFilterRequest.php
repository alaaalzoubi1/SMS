<?php

namespace App\Http\Requests;

use App\Enums\GraduationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NurseFilterRequest extends FormRequest
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
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'graduation_type' => ['nullable', Rule::in(GraduationType::values())],
            'address' => ['nullable', 'string'],
            'full_name' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'service_name' => ['nullable','string','max:50']
        ];
    }

    /**
     * Older app builds send the short spelling ("مدرسة" instead of
     * "مدرسة التمريض والقبالة"). Map any accepted alias to the canonical
     * value before validating, so the filter query matches what is stored.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('graduation_type')) {
            $this->merge([
                'graduation_type' => GraduationType::normalize((string) $this->input('graduation_type')),
            ]);
        }
    }
}

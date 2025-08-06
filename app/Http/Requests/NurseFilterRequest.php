<?php

namespace App\Http\Requests;

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
            'graduation_type' => ['nullable', Rule::in(['معهد', 'مدرسة', 'جامعة', 'ماجستير', 'دكتوراه'])],
            'address' => ['nullable', 'string'],
            'full_name' => ['nullable', 'string'],
        ];
    }
}

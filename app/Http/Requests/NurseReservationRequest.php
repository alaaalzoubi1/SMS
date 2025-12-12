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
            'nurse_service_id' => [
                'required',
                Rule::exists('nurse_services', 'id')->where('nurse_id', $this->nurse_id),
            ],
                'reservation_type' => ['required', Rule::in(['direct', 'manual'])],
            'start_at' => [
                Rule::requiredIf(fn() => $this->reservation_type === 'manual'),
                'date',
            ],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'note' => ['nullable', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'subservices' => ['nullable', 'array'],
            'subservices.*' => [
                'integer',
                Rule::exists('nurse_subservices', 'id')->where(function ($query) {
                    $query->where('service_id', $this->nurse_service_id);
                }),
            ],
            'confirm' => 'sometimes|required|boolean',
        ];
    }

}

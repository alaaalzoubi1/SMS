<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalDocumentRequest extends FormRequest
{
    /**
     * Defense-in-depth: the route itself is already gated by
     * ['auth:sanctum', 'role:super_admin'] middleware (see routes file).
     * This is a second check at the action level, in case the route
     * middleware is ever changed or this request is reused elsewhere.
     *
     * NOTE: hasRole() assumes spatie/laravel-permission. If your project
     * uses a different role mechanism (e.g. a `role` column, a custom
     * Gate), swap the line below accordingly — see README.md.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.en' => ['required', 'string', 'min:10'],
            'content.ar' => ['required', 'string', 'min:10'],
            'version' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.en.required' => 'English content is required.',
            'content.ar.required' => 'Arabic content is required.',
        ];
    }
}

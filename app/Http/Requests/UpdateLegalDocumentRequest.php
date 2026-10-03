<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalDocumentRequest extends FormRequest
{
    /**
     * Defense-in-depth: the route itself is already gated by the admin
     * middleware group (see routes/api/admin.php). This is a second check at
     * the action level, in case the route middleware is ever changed or this
     * request is reused elsewhere.
     *
     * This previously required the `super_admin` role, which RolesSeeder never
     * creates — it seeds `admin`, `doctor`, `nurse`, `user` and `hospital`.
     * Every admin save therefore failed with 403 and the client saw "editing
     * the terms and conditions does not work".
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.en' => ['required', 'string', 'min:10'],
            'content.ar' => ['required', 'string', 'min:10'],
            'version' => ['nullable', 'string', 'max:20'],
            'bump_version' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required'    => 'محتوى المستند مطلوب.',
            'content.array'       => 'محتوى المستند يجب أن يكون كائناً يحتوي على اللغات.',
            'content.en.required' => 'المحتوى الإنجليزي مطلوب.',
            'content.ar.required' => 'المحتوى العربي مطلوب.',
            'content.en.min'      => 'المحتوى الإنجليزي يجب أن يكون 10 أحرف على الأقل.',
            'content.ar.min'      => 'المحتوى العربي يجب أن يكون 10 أحرف على الأقل.',
        ];
    }
}
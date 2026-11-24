<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', Password::min(8)->letters()->numbers(), 'different:currentPassword'],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ];
    }

    public function messages(): array
    {
        return [
            'newPassword.different' => 'Password baru harus berbeda dari password lama.',
            'newPasswordConfirmation.same' => 'Konfirmasi password tidak sama.',
        ];
    }
}
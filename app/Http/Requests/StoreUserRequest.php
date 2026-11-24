<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('username'))) {
            $merge['username'] = mb_strtolower(trim($this->input('username')));
        }
        if (is_string($this->input('nik'))) {
            $merge['nik'] = mb_strtoupper(trim($this->input('nik')));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'nik' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9.\-]+$/', 'unique:users,nik'],
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'isActive' => ['nullable', 'boolean'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.regex' => 'NIK hanya boleh berisi huruf, angka, titik, dan strip.',
            'nik.unique' => 'NIK sudah digunakan.',
        ];
    }
}
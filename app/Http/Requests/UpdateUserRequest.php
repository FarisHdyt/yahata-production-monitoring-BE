<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $id = $this->route('user')->id;

        return [
            'nik' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9.\-]+$/', Rule::unique('users', 'nik')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'isActive' => ['required', 'boolean'],
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
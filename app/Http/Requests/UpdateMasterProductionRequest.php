<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partNumber' => ['required', 'string', 'max:100'],
            'rpm' => ['nullable', 'numeric', 'gt:0'],
            'pcsPerRevolution' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'partNumber.required' => 'Part Number wajib diisi.',
            'rpm.gt' => 'RPM harus lebih besar dari 0.',
            'pcsPerRevolution.gt' => 'Pcs per Revolution harus lebih besar dari 0.',
        ];
    }
}
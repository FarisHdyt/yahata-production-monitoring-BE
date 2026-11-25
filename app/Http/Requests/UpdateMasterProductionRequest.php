<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // FE uses pcsPerRev; BE historically used pcsPerRevolution.
        if (! $this->has('pcsPerRevolution') && $this->has('pcsPerRev')) {
            $this->merge(['pcsPerRevolution' => $this->input('pcsPerRev')]);
        }
    }

    public function rules(): array
    {
        return [
            'partNumber' => ['required', 'string', 'max:100'],
            'customer' => ['nullable', 'string', 'max:255'],
            'sizePart' => ['nullable', 'string', 'max:255'],
            'rpm' => ['nullable', 'numeric', 'gte:0'],
            'pcsPerRevolution' => ['nullable', 'numeric', 'gte:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'picShift1' => ['nullable', 'string', 'max:100'],
            'picShift2' => ['nullable', 'string', 'max:100'],
            'partOptions' => ['nullable', 'array'],
            'partOptions.*.partNo' => ['nullable', 'string', 'max:100'],
            'partOptions.*.customer' => ['nullable', 'string', 'max:255'],
            'partOptions.*.sizePart' => ['nullable', 'string', 'max:255'],
            'partOptions.*.rpm' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'partNumber.required' => 'Part Number wajib diisi.',
            'rpm.gte' => 'RPM tidak boleh bernilai negatif.',
            'pcsPerRevolution.gte' => 'Pcs per Revolution harus bernilai minimal 1.',
        ];
    }
}
<?php

namespace App\Http\Requests;

use App\Support\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'shift' => ['required', Rule::in(array_keys(Shift::all()))],
            'assignments' => ['required', 'array'],
            'assignments.*.machine' => ['required', 'string', Rule::exists('machines', 'name')],
            'assignments.*.pic' => ['nullable', 'string', 'max:100'],
            'assignments.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['shift' => Shift::normalize($this->input('shift'))]);
    }
}

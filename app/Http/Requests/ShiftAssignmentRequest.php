<?php

namespace App\Http\Requests;

use App\Models\Machine;
use App\Models\ShiftAssignment;
use App\Support\Shift;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['shift' => Shift::normalize($this->input('shift'))]);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'shift' => ['required', Rule::in(array_keys(Shift::all()))],
            'machine' => ['required', 'string', Rule::exists('machines', 'name')],
            'pic' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['pic.required' => 'Nama PIC wajib diisi.'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $machine = Machine::where('name', $this->input('machine'))->first();
            $ignoreId = $this->route('assignment')?->id;

            $duplicate = ShiftAssignment::query()
                ->whereDate('date', $this->input('date'))
                ->where('shift', $this->input('shift'))
                ->where('machine_id', $machine->id)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($duplicate) {
                $v->errors()->add(
                    'machine',
                    'Mesin ini sudah memiliki penugasan PIC pada tanggal dan shift tersebut.'
                );
            }
        });
    }
}
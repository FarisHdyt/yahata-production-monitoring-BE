<?php

namespace App\Http\Requests;

use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Support\Shift;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionRecordRequest extends FormRequest
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
            'partNumber' => ['required', 'string', 'max:100'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'output' => ['required', 'integer', 'min:1'],
            'pic' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'partNumber.required' => 'Mesin belum memiliki Part Number. Periksa Master Production terlebih dahulu.',
            'output.integer' => 'Output harus berupa bilangan bulat lebih dari 0.',
            'output.min' => 'Output harus berupa bilangan bulat lebih dari 0.',
            'pic.required' => 'Nama PIC wajib diisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $shift = Shift::get((int) $this->input('shift'));
            $start = $this->input('startTime');
            $end = $this->input('endTime');

            if ($start >= $end || $start < $shift['start'] || $end > $shift['end']) {
                $v->errors()->add(
                    'startTime',
                    "Waktu produksi harus berada dalam jadwal {$shift['name']}: {$shift['start']} - {$shift['end']}."
                );

                return;
            }

            $machine = Machine::where('name', $this->input('machine'))->first();
            $ignoreId = $this->route('record')?->id;

            $overlap = ProductionRecord::query()
                ->whereDate('date', $this->input('date'))
                ->where('machine_id', $machine->id)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('start_time', '<', $end . ':00')
                ->where('end_time', '>', $start . ':00')
                ->exists();

            if ($overlap) {
                $v->errors()->add(
                    'startTime',
                    'Periode produksi bertabrakan dengan catatan lain pada mesin dan tanggal yang sama.'
                );
            }
        });
    }
}
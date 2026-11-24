<?php

namespace App\Http\Requests;

use App\Models\DowntimeCategory;
use App\Models\DowntimeEvent;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine' => ['required', 'string', Rule::exists('machines', 'name')],
            // boleh kirim nama kategori (seperti frontend) atau kode kategori
            'category' => [
                'required_without:categoryCode', 'nullable', 'string',
                Rule::exists('downtime_categories', 'name')->where('is_downtime', true),
            ],
            'categoryCode' => [
                'required_without:category', 'nullable', 'integer',
                Rule::exists('downtime_categories', 'code')->where('is_downtime', true),
            ],
            'startAt' => ['required', 'date'],
            'endAt' => ['nullable', 'date', 'after:startAt'],
            'pic' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'startAt.required' => 'Waktu mulai wajib diisi.',
            'endAt.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }

    // ---- helper dipakai controller ----

    public function machineModel(): Machine
    {
        return Machine::where('name', $this->input('machine'))->firstOrFail();
    }

    public function categoryCode(): int
    {
        return $this->filled('categoryCode')
            ? (int) $this->input('categoryCode')
            : (int) DowntimeCategory::where('name', $this->input('category'))->value('code');
    }

    public function startAt(): Carbon
    {
        return Carbon::parse($this->input('startAt'))->setTimezone(config('app.timezone'));
    }

    public function endAt(): ?Carbon
    {
        return $this->filled('endAt')
            ? Carbon::parse($this->input('endAt'))->setTimezone(config('app.timezone'))
            : null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $machine = $this->machineModel();
            $ignoreId = $this->route('event')?->id;
            $start = $this->startAt();
            $end = $this->endAt();
            $now = now();

            if (! $end && DowntimeEvent::where('machine_id', $machine->id)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->whereNull('end_at')->exists()) {
                $v->errors()->add('machine', 'Mesin ini masih memiliki downtime aktif.');

                return;
            }

            // downtime yang belum selesai dianggap berakhir "sekarang" (sama seperti frontend)
            $newEnd = $end ?? $now;

            $overlap = DowntimeEvent::where('machine_id', $machine->id)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('start_at', '<', $newEnd)
                ->where(function ($q) use ($start, $now) {
                    $q->where('end_at', '>', $start);
                    if ($now->gt($start)) {
                        $q->orWhereNull('end_at');
                    }
                })
                ->exists();

            if ($overlap) {
                $v->errors()->add('startAt', 'Waktu downtime bertabrakan dengan catatan mesin yang sudah ada.');
            }
        });
    }
}
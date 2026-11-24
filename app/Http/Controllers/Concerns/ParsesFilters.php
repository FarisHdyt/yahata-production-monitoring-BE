<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Shift;
use Illuminate\Http\Request;

trait ParsesFilters
{
    /** Query string -> filter bersih. Nilai kosong / "all" dianggap tidak ada filter. */
    protected function filters(Request $request): array
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d'],
            'shift' => ['nullable', 'string', 'max:20'],
            'machine' => ['nullable', 'string', 'max:100'],
            'partNumber' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,finished'],
        ]);

        $clean = function (string $key) use ($request) {
            $value = $request->query($key);

            return in_array($value, [null, '', 'all'], true) ? null : $value;
        };

        return [
            'date' => $clean('date'),
            'startDate' => $clean('startDate'),
            'endDate' => $clean('endDate'),
            'shift' => Shift::normalize($request->query('shift')),
            'machine' => $clean('machine'),
            'partNumber' => $clean('partNumber'),
            'status' => $clean('status'),
        ];
    }
}
<?php

namespace App\Http\Resources;

use App\Support\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DowntimeEvent */
class DowntimeEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine' => $this->machine?->name,
            'machineId' => $this->machine_id,
            'category' => $this->category?->name,
            'categoryCode' => $this->category_code,
            'startAt' => $this->start_at->toIso8601String(),
            'endAt' => $this->end_at?->toIso8601String(),
            'shift' => Shift::name($this->shift),
            'shiftId' => $this->shift,
            'durationMinutes' => $this->durationMinutes(),
            'status' => $this->end_at ? 'Selesai' : 'Aktif',
            'pic' => $this->pic ?? '',
            'notes' => $this->notes ?? '',
        ];
    }
}
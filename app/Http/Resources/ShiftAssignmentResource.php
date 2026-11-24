<?php

namespace App\Http\Resources;

use App\Support\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShiftAssignment */
class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'shift' => Shift::name($this->shift),
            'shiftId' => $this->shift,
            'schedule' => Shift::schedule($this->shift),
            'machine' => $this->machine?->name,
            'machineId' => $this->machine_id,
            'pic' => $this->pic,
            'notes' => $this->notes ?? '',
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
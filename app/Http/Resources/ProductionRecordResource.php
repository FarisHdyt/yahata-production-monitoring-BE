<?php

namespace App\Http\Resources;

use App\Support\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductionRecord */
class ProductionRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $start = substr($this->start_time, 0, 5);
        $end = substr($this->end_time, 0, 5);

        // Target teoritis = RPM x pcs/revolusi x menit produksi (asumsi: RPM = putaran per menit).
        $mp = $this->machine?->masterProduction;
        $minutes = Shift::toMinutes($end) - Shift::toMinutes($start);
        $target = ($mp?->rpm && $mp?->pcs_per_revolution)
            ? (int) round($mp->rpm * $mp->pcs_per_revolution * $minutes)
            : null;

        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'shift' => $this->shift,
            'machine' => $this->machine?->name,
            'machineId' => $this->machine_id,
            'partNumber' => $this->part_number,
            'startTime' => $start,
            'endTime' => $end,
            'counterStart' => $this->counter_start,
            'counterEnd' => $this->counter_end,
            'rpm' => $this->rpm ?? $mp?->rpm,
            'output' => $this->output,
            'targetOutput' => $target,
            'productivity' => $target ? round($this->output / $target * 100, 1) : null,
            'pic' => $this->pic,
            'notes' => $this->notes ?? '',
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
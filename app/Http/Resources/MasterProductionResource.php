<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Machine */
class MasterProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mp = $this->masterProduction;

        return [
            'machineId' => $this->id,
            'machine' => $this->name,
            'partNumber' => $mp?->part_number ?? '',
            'customer' => $mp?->customer ?? '',
            'sizePart' => $mp?->size_part ?? '',
            'rpm' => $mp?->rpm,
            'pcsPerRevolution' => $mp?->pcs_per_revolution,
            'pcsPerRev' => $mp?->pcs_per_revolution,
            'picShift1' => $mp?->pic_shift1 ?? '',
            'picShift2' => $mp?->pic_shift2 ?? '',
            'partOptions' => $mp?->part_options ?? [],
            'notes' => $mp?->notes ?? '',
            'isConfigured' => filled($mp?->part_number),
            'updatedAt' => $mp?->updated_at?->toIso8601String(),
        ];
    }
}
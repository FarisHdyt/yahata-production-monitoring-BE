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
            'rpm' => $mp?->rpm,
            'pcsPerRevolution' => $mp?->pcs_per_revolution,
            'notes' => $mp?->notes ?? '',
            'isConfigured' => filled($mp?->part_number),
            'updatedAt' => $mp?->updated_at?->toIso8601String(),
        ];
    }
}
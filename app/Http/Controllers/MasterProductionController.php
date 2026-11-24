<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMasterProductionRequest;
use App\Http\Resources\MasterProductionResource;
use App\Models\Machine;
use App\Models\MasterProduction;

class MasterProductionController extends Controller
{
    public function index()
    {
        $machines = Machine::with('masterProduction')->orderBy('name')->get();
        $configured = $machines->filter(fn ($m) => filled($m->masterProduction?->part_number))->count();

        return MasterProductionResource::collection($machines)->additional([
            'summary' => [
                'totalMachines' => $machines->count(),
                'configured' => $configured,
                'unconfigured' => $machines->count() - $configured,
                'completeness' => $machines->count()
                    ? (int) round($configured / $machines->count() * 100)
                    : 0,
            ],
        ]);
    }

    public function show(Machine $machine)
    {
        return new MasterProductionResource($machine->load('masterProduction'));
    }

    // Upsert konfigurasi mesin
    public function update(UpdateMasterProductionRequest $request, Machine $machine)
    {
        MasterProduction::updateOrCreate(
            ['machine_id' => $machine->id],
            [
                'part_number' => $request->input('partNumber'),
                'rpm' => $request->input('rpm'),
                'pcs_per_revolution' => $request->input('pcsPerRevolution'),
                'notes' => $request->input('notes'),
            ]
        );

        return new MasterProductionResource($machine->load('masterProduction'));
    }
}
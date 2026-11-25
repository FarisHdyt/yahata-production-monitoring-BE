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
                'customer' => $request->input('customer'),
                'size_part' => $request->input('sizePart'),
                'rpm' => $request->input('rpm'),
                'pcs_per_revolution' => $request->input('pcsPerRevolution', $request->input('pcsPerRev', 1)),
                'notes' => $request->input('notes'),
                'pic_shift1' => $request->input('picShift1'),
                'pic_shift2' => $request->input('picShift2'),
                'part_options' => $request->input('partOptions', []),
            ]
        );

        return new MasterProductionResource($machine->load('masterProduction'));
    }
}
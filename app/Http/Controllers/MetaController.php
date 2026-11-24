<?php

namespace App\Http\Controllers;

use App\Models\DowntimeCategory;
use App\Models\Machine;
use App\Models\MasterProduction;
use App\Models\ProductionRecord;
use App\Support\Shift;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function index(): JsonResponse
    {
        $partNumbers = MasterProduction::whereNotNull('part_number')->pluck('part_number')
            ->merge(ProductionRecord::distinct()->pluck('part_number'))
            ->filter()->unique()->sort()->values();

        return response()->json(['data' => [
            'timezone' => config('app.timezone'),
            'serverTime' => now()->toIso8601String(),
            'machines' => Machine::orderBy('name')->get(['id', 'name']),
            'shifts' => collect(Shift::all())->map(fn ($s, $id) => [
                'id' => $id,
                'key' => $s['key'],
                'name' => $s['name'],
                'start' => $s['start'],
                'end' => $s['end'],
                'schedule' => "{$s['start']} - {$s['end']}",
            ])->values(),
            'downtimeCategories' => DowntimeCategory::orderBy('code')->get()
                ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'isDowntime' => $c->is_downtime]),
            'partNumbers' => $partNumbers,
        ]]);
    }
}
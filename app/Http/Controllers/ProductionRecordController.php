<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesFilters;
use App\Http\Requests\ProductionRecordRequest;
use App\Http\Resources\ProductionRecordResource;
use App\Models\Machine;
use App\Models\ProductionRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductionRecordController extends Controller
{
    use ParsesFilters;

    public function index(Request $request)
    {
        $records = ProductionRecord::with('machine.masterProduction')
            ->filter($this->filters($request))
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        $today = now()->toDateString();

        return ProductionRecordResource::collection($records)->additional([
            'summary' => [
                'filteredCount' => $records->count(),
                'totalRecords' => ProductionRecord::count(),
                'todayRecords' => ProductionRecord::whereDate('date', $today)->count(),
                'todayOutput' => (int) ProductionRecord::whereDate('date', $today)->sum('output'),
                'allTimeOutput' => (int) ProductionRecord::sum('output'),
            ],
        ]);
    }

    public function store(ProductionRecordRequest $request)
    {
        $record = ProductionRecord::create($this->payload($request));

        return (new ProductionRecordResource($record->load('machine.masterProduction')))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ProductionRecord $record)
    {
        return new ProductionRecordResource($record->load('machine.masterProduction'));
    }

    public function update(ProductionRecordRequest $request, ProductionRecord $record)
    {
        $record->update($this->payload($request));

        return new ProductionRecordResource($record->load('machine.masterProduction'));
    }

    public function destroy(ProductionRecord $record)
    {
        $record->delete();

        return response()->noContent();
    }

    private function payload(ProductionRecordRequest $request): array
    {
        return [
            'date' => $request->input('date'),
            'shift' => (int) $request->input('shift'),
            'machine_id' => Machine::where('name', $request->input('machine'))->value('id'),
            'part_number' => $request->input('partNumber'),
            'start_time' => $request->input('startTime') . ':00',
            'end_time' => $request->input('endTime') . ':00',
            'counter_start' => $request->filled('counterStart') ? (int) $request->input('counterStart') : null,
            'counter_end' => $request->filled('counterEnd') ? (int) $request->input('counterEnd') : null,
            'rpm' => $request->filled('rpm') ? (float) $request->input('rpm') : null,
            'output' => (int) $request->input('output'),
            'pic' => $request->input('pic'),
            'notes' => $request->input('notes'),
        ];
    }
}
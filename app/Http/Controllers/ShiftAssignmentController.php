<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesFilters;
use App\Http\Requests\ShiftAssignmentRequest;
use App\Http\Resources\ShiftAssignmentResource;
use App\Models\Machine;
use App\Models\ShiftAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShiftAssignmentController extends Controller
{
    use ParsesFilters;

    public function index(Request $request)
    {
        $f = $this->filters($request);

        $assignments = ShiftAssignment::with('machine')
            ->filter($f)
            ->orderByDesc('date')
            ->get()
            ->sortBy([['date', 'desc'], ['machine.name', 'asc']])
            ->values();

        // Kartu statistik di frontend = penugasan pada tanggal tertentu (default hari ini), lintas shift
        $statsDate = $f['date'] ?? now()->toDateString();
        $day = ShiftAssignment::whereDate('date', $statsDate)->get();
        $totalMachines = Machine::count();
        $assigned = $day->pluck('machine_id')->unique()->count();

        return ShiftAssignmentResource::collection($assignments)->additional([
            'summary' => [
                'date' => $statsDate,
                'filteredCount' => $assignments->count(),
                'shift1Count' => $day->where('shift', 1)->count(),
                'shift2Count' => $day->where('shift', 2)->count(),
                'totalMachines' => $totalMachines,
                'assignedMachines' => $assigned,
                'unassignedMachines' => $totalMachines - $assigned,
                'totalAssignments' => $day->count(),
            ],
        ]);
    }

    public function store(ShiftAssignmentRequest $request)
    {
        $assignment = ShiftAssignment::create($this->payload($request));

        return (new ShiftAssignmentResource($assignment->load('machine')))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ShiftAssignment $assignment)
    {
        return new ShiftAssignmentResource($assignment->load('machine'));
    }

    public function update(ShiftAssignmentRequest $request, ShiftAssignment $assignment)
    {
        $assignment->update($this->payload($request));

        return new ShiftAssignmentResource($assignment->load('machine'));
    }

    public function destroy(ShiftAssignment $assignment)
    {
        $assignment->delete();

        return response()->noContent();
    }

    private function payload(ShiftAssignmentRequest $request): array
    {
        return [
            'date' => $request->input('date'),
            'shift' => (int) $request->input('shift'),
            'machine_id' => Machine::where('name', $request->input('machine'))->value('id'),
            'pic' => $request->input('pic'),
            'notes' => $request->input('notes'),
        ];
    }
}
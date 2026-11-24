<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesFilters;
use App\Http\Requests\DowntimeEventRequest;
use App\Http\Resources\DowntimeEventResource;
use App\Models\DowntimeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DowntimeEventController extends Controller
{
    use ParsesFilters;

    public function index(Request $request)
    {
        $now = now();

        $events = DowntimeEvent::with(['machine', 'category'])
            ->filter($this->filters($request))
            ->orderByDesc('start_at')
            ->get();

        $counted = $events->where('category_code', '!=', 1);

        $categories = $counted->groupBy(fn ($e) => $e->category->name)
            ->map(fn ($group, $name) => [
                'category' => $name,
                'count' => $group->count(),
                'minutes' => $group->sum(fn ($e) => $e->durationMinutes($now)),
            ])
            ->sortByDesc('minutes')->values();

        return DowntimeEventResource::collection($events)->additional([
            'summary' => [
                'totalCount' => $counted->count(),
                'totalMinutes' => $counted->sum(fn ($e) => $e->durationMinutes($now)),
                'activeCount' => $counted->whereNull('end_at')->count(),
                'affectedMachines' => $counted->pluck('machine_id')->unique()->count(),
                'categories' => $categories,
            ],
        ]);
    }

    public function store(DowntimeEventRequest $request)
    {
        $event = DowntimeEvent::create($this->payload($request));

        return (new DowntimeEventResource($event->load(['machine', 'category'])))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DowntimeEvent $event)
    {
        return new DowntimeEventResource($event->load(['machine', 'category']));
    }

    public function update(DowntimeEventRequest $request, DowntimeEvent $event)
    {
        $event->update($this->payload($request));

        return new DowntimeEventResource($event->load(['machine', 'category']));
    }

    // Tombol "Selesaikan downtime": end_at = sekarang
    public function finish(DowntimeEvent $event)
    {
        if ($event->end_at !== null) {
            return response()->json(['message' => 'Downtime ini sudah selesai.'], 422);
        }

        $now = now();

        if ($now->lte($event->start_at)) {
            return response()->json(['message' => 'Waktu selesai tidak valid.'], 422);
        }

        $event->update(['end_at' => $now]);

        return new DowntimeEventResource($event->load(['machine', 'category']));
    }

    public function destroy(DowntimeEvent $event)
    {
        $event->delete();

        return response()->noContent();
    }

    private function payload(DowntimeEventRequest $request): array
    {
        return [
            'machine_id' => $request->machineModel()->id,
            'category_code' => $request->categoryCode(),
            'start_at' => $request->startAt(),
            'end_at' => $request->endAt(),
            'pic' => $request->input('pic'),
            'notes' => $request->input('notes'),
        ];
    }
}
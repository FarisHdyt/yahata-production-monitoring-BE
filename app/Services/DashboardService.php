<?php

namespace App\Services;

use App\Models\DowntimeEvent;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\ShiftAssignment;
use App\Support\Shift;
use Illuminate\Support\Collection;

class DashboardService
{
    public const STATUS_LABELS = [
        'running' => 'Running',
        'downtime' => 'Downtime',
        'stop' => 'Stop',
        'unconfirmed' => 'Belum dikonfirmasi',
        'needs_confirmation' => 'Perlu konfirmasi',
    ];

    public function build(string $date, ?int $shift, ?string $machineName, ?string $partNumber): array
    {
        $now = now();
        $isToday = $date === $now->toDateString();
        $currentShift = Shift::forDateTime($now);

        $machines = Machine::with('masterProduction')
            ->when($machineName, fn ($q) => $q->where('name', $machineName))
            ->orderBy('name')->get();
        $ids = $machines->pluck('id');

        $records = ProductionRecord::whereDate('date', $date)
            ->whereIn('machine_id', $ids)
            ->when($shift, fn ($q) => $q->where('shift', $shift))
            ->orderBy('start_time')->get()
            ->groupBy('machine_id');

        $assignments = ShiftAssignment::whereDate('date', $date)
            ->whereIn('machine_id', $ids)
            ->when($shift, fn ($q) => $q->where('shift', $shift))
            ->orderBy('shift')
            ->get()->groupBy('machine_id');

        // Downtime aktif hanya relevan bila tanggal yang dilihat = hari ini
        $activeNow = $isToday
            ? DowntimeEvent::with('category')->counted()->active($now)
                ->whereIn('machine_id', $ids)->get()->keyBy('machine_id')
            : collect();

        $rows = $machines->map(function (Machine $m) use ($records, $assignments, $activeNow, $isToday, $currentShift, $shift, $now) {
            $recs = $records->get($m->id, collect());
            $master = $m->masterProduction;

            $actual = $recs->isEmpty() ? null : (int) $recs->sum('output');
            $latest = $recs->last();
            $active = $activeNow->get($m->id);

            $assign = $assignments->get($m->id);
            $assignmentShift = $shift ?? ($isToday ? $currentShift : null);
            $assignment = $assign
                ? ($assignmentShift === null
                    ? $assign->first()
                    : ($assign->firstWhere('shift', $assignmentShift) ?? $assign->first()))
                : null;

            $status = $this->status($active !== null, $actual, $isToday, $now, filled($master?->part_number));

            return [
                'machineId' => $m->id,
                'name' => $m->name,
                'status' => $status,
                'statusLabel' => self::STATUS_LABELS[$status],
                'actual' => $actual,
                'partNumber' => $latest?->part_number ?: ($master?->part_number ?: '-'),
                'pic' => $latest?->pic ?: ($assignment?->pic ?: '-'),
                'rpm' => $master?->rpm,
                'downtime' => $active?->category?->name,
                'duration' => $active?->durationMinutes($now),
            ];
        })
            ->filter(fn ($r) => ! $partNumber || $r['partNumber'] === $partNumber)
            ->values();

        $visibleIds = $rows->pluck('machineId');

        // Downtime pada tanggal/shift terpilih untuk mesin yang tampil
        $events = DowntimeEvent::with('category')->counted()
            ->whereIn('machine_id', $visibleIds)
            ->whereDate('start_at', $date)
            ->when($shift, fn ($q) => $q->where('shift', $shift))
            ->get();

        $downtime = $events->groupBy(fn ($e) => $e->category->name)
            ->map(fn ($g, $name) => [
                'category' => $name,
                'minutes' => $g->sum(fn ($e) => $e->durationMinutes($now)),
                'count' => $g->count(),
            ])
            ->sortByDesc('minutes')->values();

        $visibleRecords = $records->only($visibleIds->all())->collapse();

        return [
            'date' => $date,
            'shift' => $shift,
            'generatedAt' => $now->toIso8601String(),
            'kpi' => [
                'totalOutput' => (int) $rows->sum(fn ($r) => $r['actual'] ?? 0),
                'totalMachines' => $rows->count(),
                'running' => $rows->where('status', 'running')->count(),
                'downtime' => $rows->where('status', 'downtime')->count(),
                'validData' => $rows->filter(fn ($r) => $r['actual'] !== null)->count(),
                'downtimeCount' => $events->count(),
                'downtimeMinutes' => $downtime->sum('minutes'),
            ],
            'machines' => $rows,
            'trend' => $this->hourlyTrend($visibleRecords, $shift),
            'downtime' => $downtime,
            'totalDowntime' => $downtime->sum('minutes'),
        ];
    }

    /**
     * Status tidak boleh otomatis "running" hanya karena tidak ada downtime.
     * Urutan: downtime aktif -> ada output tercatat (running) -> di luar jadwal (stop)
     *         -> sudah punya part number (perlu konfirmasi) -> belum dikonfigurasi.
     */
    private function status(bool $hasActiveDowntime, ?int $actual, bool $isToday, $now, bool $hasPartNumber): string
    {
        if ($hasActiveDowntime) {
            return 'downtime';
        }
        if ($actual !== null && $actual > 0) {
            return 'running';
        }
        if ($isToday && Shift::forDateTime($now) === null) {
            return 'stop';
        }

        return $hasPartNumber ? 'needs_confirmation' : 'unconfirmed';
    }

    /**
     * Output per jam. Satu catatan produksi (misal 07:00-10:00, 3000 pcs)
     * dibagi proporsional ke jam-jam yang dilewatinya.
     */
    private function hourlyTrend(Collection $records, ?int $shift): array
    {
        $shifts = $shift ? [Shift::get($shift)] : array_values(Shift::all());
        $from = Shift::toMinutes(collect($shifts)->min('start'));
        $to = Shift::toMinutes(collect($shifts)->max('end'));

        $buckets = [];
        for ($h = intdiv($from, 60); $h * 60 < $to; $h++) {
            $buckets[$h] = 0.0;
        }

        foreach ($records as $r) {
            $start = Shift::toMinutes(substr($r->start_time, 0, 5));
            $end = Shift::toMinutes(substr($r->end_time, 0, 5));
            $duration = $end - $start;

            if ($duration <= 0) {
                continue;
            }

            foreach ($buckets as $h => $_) {
                $overlap = min($end, ($h + 1) * 60) - max($start, $h * 60);
                if ($overlap > 0) {
                    $buckets[$h] += $r->output * $overlap / $duration;
                }
            }
        }

        $out = [];
        foreach ($buckets as $h => $value) {
            $out[] = ['hour' => sprintf('%02d:00', $h), 'output' => (int) round($value)];
        }

        return $out;
    }
}
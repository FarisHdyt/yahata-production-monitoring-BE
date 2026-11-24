<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesFilters;
use App\Http\Resources\DowntimeEventResource;
use App\Models\DowntimeEvent;
use App\Models\ProductionRecord;
use App\Support\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ParsesFilters;

    // ============== DOWNTIME ==============

    public function downtime(Request $request)
    {
        $events = $this->downtimeEvents($this->filters($request));
        $now = now();

        return DowntimeEventResource::collection($events)->additional([
            'summary' => [
                'totalCount' => $events->count(),
                'totalMinutes' => $events->sum(fn ($e) => $e->durationMinutes($now)),
                'activeCount' => $events->whereNull('end_at')->count(),
                'affectedMachines' => $events->pluck('machine_id')->unique()->count(),
            ],
            'categories' => $this->categorySummary($events),
            'shifts' => $this->shiftSummary($events),
        ]);
    }

    public function exportDowntime(Request $request): StreamedResponse
    {
        $events = $this->downtimeEvents($this->filters($request));

        $rows = [[
            'Tanggal', 'Shift', 'Mesin', 'Kategori Downtime', 'Mulai', 'Selesai',
            'Durasi (Menit)', 'PIC', 'Catatan', 'Status',
        ]];

        foreach ($events as $e) {
            $rows[] = [
                $e->start_at->format('d/m/Y'),
                Shift::name($e->shift),
                $e->machine->name,
                $e->category->name,
                $e->start_at->format('d/m/Y H:i:s'),
                $e->end_at ? $e->end_at->format('d/m/Y H:i:s') : 'Masih berlangsung',
                $e->durationMinutes(),
                $e->pic ?: '-',
                $e->notes ?: '-',
                $e->end_at ? 'Selesai' : 'Aktif',
            ];
        }

        return $this->csv('Yahata_Downtime_Report.csv', $rows);
    }

    public function exportDowntimeSummary(Request $request): StreamedResponse
    {
        $events = $this->downtimeEvents($this->filters($request));
        $categories = $this->categorySummary($events);

        $rows = [['Kategori Downtime', 'Jumlah Kejadian', 'Total Durasi (Menit)']];
        foreach ($categories as $c) {
            $rows[] = [$c['category'], $c['count'], $c['minutes']];
        }
        $rows[] = [];
        $rows[] = ['Total Downtime', $events->count(), $categories->sum('minutes')];

        return $this->csv('Yahata_Downtime_Summary.csv', $rows);
    }

    // ============== PRODUKSI ==============

    public function production(Request $request)
    {
        return response()->json(['data' => $this->productionReport($this->filters($request))]);
    }

    public function exportProduction(Request $request): StreamedResponse
    {
        $report = $this->productionReport($this->filters($request));

        $rows = [['Tanggal', 'Actual Shift 1', 'Actual Shift 2', 'Total Actual', 'Jumlah Downtime', 'Durasi Downtime (menit)']];
        foreach ($report['daily'] as $d) {
            $rows[] = [
                $d['date'], $d['actualShift1'] ?? '', $d['actualShift2'] ?? '',
                $d['totalActual'], $d['downtimeCount'], $d['downtimeMinutes'],
            ];
        }

        return $this->csv('Yahata_Production_Report.csv', $rows);
    }

    // ============== helpers ==============

    private function downtimeEvents(array $f): Collection
    {
        return DowntimeEvent::with(['machine', 'category'])
            ->counted()
            ->filter(Arr::only($f, ['startDate', 'endDate', 'shift', 'machine']))
            ->orderByDesc('start_at')
            ->get();
    }

    private function categorySummary(Collection $events): Collection
    {
        $now = now();

        return $events->groupBy(fn ($e) => $e->category->name)
            ->map(fn ($g, $name) => [
                'category' => $name,
                'count' => $g->count(),
                'minutes' => $g->sum(fn ($e) => $e->durationMinutes($now)),
            ])
            ->sortByDesc('minutes')->values();
    }

    private function shiftSummary(Collection $events): array
    {
        $now = now();

        return collect(Shift::all())->map(function ($s, $id) use ($events, $now) {
            $match = $events->where('shift', $id);

            return [
                'shift' => $s['name'],
                'count' => $match->count(),
                'minutes' => $match->sum(fn ($e) => $e->durationMinutes($now)),
            ];
        })->values()->all();
    }

    private function productionReport(array $f): array
    {
        $recordScope = Arr::only($f, ['startDate', 'endDate', 'shift', 'machine', 'partNumber']);
        $eventScope = Arr::only($f, ['startDate', 'endDate', 'shift', 'machine']);

        $records = ProductionRecord::with('machine')->filter($recordScope)
            ->orderBy('date')->orderBy('start_time')->get();

        $events = DowntimeEvent::counted()->filter($eventScope)->get();

        $dates = $records->map(fn ($r) => $r->date->toDateString())
            ->merge($events->map(fn ($e) => $e->start_at->toDateString()))
            ->unique()->sort()->values();

        $sumShift = function (Collection $rs, int $shift) {
            $s = $rs->where('shift', $shift);

            return $s->isEmpty() ? null : (int) $s->sum('output');
        };

        $daily = $dates->map(function ($date) use ($records, $events, $sumShift) {
            $rs = $records->filter(fn ($r) => $r->date->toDateString() === $date);
            $es = $events->filter(fn ($e) => $e->start_at->toDateString() === $date);

            return [
                'date' => $date,
                'actualShift1' => $sumShift($rs, 1),
                'actualShift2' => $sumShift($rs, 2),
                'totalActual' => (int) $rs->sum('output'),
                'downtimeCount' => $es->count(),
                'downtimeMinutes' => $es->sum(fn ($e) => $e->durationMinutes()),
            ];
        })->values();

        $perMachine = $records->groupBy(fn ($r) => $r->machine->name)
            ->map(fn ($g, $name) => [
                'machine' => $name,
                'records' => $g->count(),
                'output' => (int) $g->sum('output'),
            ])->sortBy('machine')->values();

        $perShift = collect(Shift::all())->map(fn ($s, $id) => [
            'shift' => $s['name'],
            'output' => (int) $records->where('shift', $id)->sum('output'),
            'records' => $records->where('shift', $id)->count(),
        ])->values();

        return [
            'summary' => [
                'totalActual' => (int) $records->sum('output'),
                'totalRecords' => $records->count(),
                'totalDowntimeCount' => $events->count(),
                'totalDowntimeMinutes' => $events->sum(fn ($e) => $e->durationMinutes()),
            ],
            'daily' => $daily,
            'shifts' => $perShift,
            'machines' => $perMachine,
        ];
    }

    private function csv(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8

            foreach ($rows as $row) {
                // cegah CSV/formula injection di Excel
                fputcsv($out, array_map(function ($cell) {
                    return is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'" . $cell : $cell;
                }, $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
<?php

namespace Database\Seeders;

use App\Models\DowntimeCategory;
use App\Models\DowntimeEvent;
use App\Models\Machine;
use App\Models\MasterProduction;
use App\Models\ProductionRecord;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * DATA CONTOH untuk development/demo. JANGAN dijalankan di production.
 *
 * Isi: akun operator (sesuai PIC di FE), plotting PIC, catatan produksi dan downtime
 * untuk DAYS hari terakhir termasuk hari ini (tanggal relatif terhadap hari ini).
 *
 * Idempotent: dijalankan ulang tidak menggandakan data. Semua data mematuhi aturan validasi API
 * (di dalam jadwal shift, tidak overlap, satu downtime aktif per mesin, unik tanggal+shift+mesin).
 */
class DemoSeeder extends Seeder
{
    private const DAYS = 7;

    /** username => nama. NIK null. Password dari SEED_DEMO_PASSWORD. */
    private const OPERATORS = [
        'iis' => 'Iis Iskandar',
        'achmad' => 'Achmad Syaiful',
        'nanda' => 'Nanda Ferry C',
        'fiki' => 'Fiki Firmansyah',
        'yossi' => 'Yossi Tera P',
        'wakyudi' => 'Wakyudi',
        'jason' => 'Jason',
    ];

    /** Kategori downtime yang dipakai demo (kode 2-12, semua is_downtime = true). */
    private const DOWNTIME_CODES = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    public function run(): void
    {
        if (app()->environment('production') && ! filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->error('DemoSeeder ditolak di production. Set SEED_DEMO=true jika memang disengaja.');

            return;
        }

        $this->seedOperators();

        $machines = Machine::orderBy('name')->get()->keyBy('name');
        $masters = MasterProduction::with('machine')->get()->keyBy(fn ($m) => $m->machine->name);

        if ($machines->isEmpty() || $masters->isEmpty()) {
            $this->command?->warn('Machine/MasterProduction kosong. Jalankan MachineSeeder & MasterProductionSeeder dulu.');

            return;
        }

        $now = now();
        $today = $now->copy()->startOfDay();
        $counters = [];

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);

            foreach ($masters as $name => $master) {
                $machine = $machines[$name];

                foreach ([1 => $master->pic_shift1, 2 => $master->pic_shift2] as $shift => $pic) {
                    if (! $pic) {
                        continue; // mesin tidak berjalan di shift ini (mis. ZH-30200 hanya shift 1)
                    }

                    $this->seedAssignment($date, $shift, $machine, $pic);

                    $cfg = config("yahata.shifts.{$shift}");
                    $end = $date->copy()->setTimeFromTimeString($cfg['end']);

                    // Shift yang belum selesai (mis. hari ini) tidak diberi catatan produksi.
                    if ($end->greaterThan($now)) {
                        continue;
                    }

                    $this->seedProduction($date, $shift, $cfg, $machine, $master, $pic, $i, $counters);
                }

                $this->seedFinishedDowntime($date, $machine, $master, $i);
            }
        }

        $this->seedActiveDowntime($now, $machines, $masters);
    }

    private function seedOperators(): void
    {
        $password = env('SEED_DEMO_PASSWORD', 'Operator12345');

        foreach (self::OPERATORS as $username => $name) {
            User::firstOrCreate(
                ['username' => $username],
                [
                    'nik' => null,
                    'name' => $name,
                    'role' => 'operator',
                    'is_active' => true,
                    'password' => $password, // plain: di-hash oleh cast 'hashed'
                ]
            );
        }
    }

    private function seedAssignment(Carbon $date, int $shift, Machine $machine, string $pic): void
    {
        // whereDate: aman di PostgreSQL (kolom date) maupun SQLite (tersimpan sebagai datetime).
        $row = ShiftAssignment::whereDate('date', $date->toDateString())
            ->where('shift', $shift)
            ->where('machine_id', $machine->id)
            ->first();

        $row
            ? $row->update(['pic' => $pic])
            : ShiftAssignment::create([
                'date' => $date->toDateString(), 'shift' => $shift, 'machine_id' => $machine->id, 'pic' => $pic,
            ]);
    }

    private function seedProduction(
        Carbon $date,
        int $shift,
        array $cfg,
        Machine $machine,
        MasterProduction $master,
        string $pic,
        int $daysAgo,
        array &$counters
    ): void {
        $start = $cfg['start'];
        $end = $cfg['end'];
        $minutes = \App\Support\Shift::toMinutes($end) - \App\Support\Shift::toMinutes($start);

        $rpm = (float) $master->rpm;
        $pcsPerRev = max(1.0, (float) $master->pcs_per_revolution);

        // Waktu efektif ~ 87% (istirahat) dikali efisiensi 70-95% yang deterministik per hari/mesin/shift.
        $efficiency = 0.70 + (abs(crc32("{$machine->name}|{$date->toDateString()}|{$shift}")) % 26) / 100;
        $output = max(1, (int) round($rpm * $pcsPerRev * $minutes * 0.87 * $efficiency));

        $key = $machine->id;
        $counters[$key] ??= 100000 + (abs(crc32($machine->name)) % 50000);
        $counterStart = $counters[$key];
        $counterEnd = $counterStart + (int) round($output / $pcsPerRev);
        $counters[$key] = $counterEnd;

        $attrs = [
            'part_number' => $master->part_number,
            'end_time' => $end . ':00',
            'counter_start' => $counterStart,
            'counter_end' => $counterEnd,
            'rpm' => $rpm,
            'output' => $output,
            'pic' => $pic,
            'notes' => null,
        ];

        $row = ProductionRecord::whereDate('date', $date->toDateString())
            ->where('shift', $shift)
            ->where('machine_id', $machine->id)
            ->where('start_time', $start . ':00')
            ->first();

        $row
            ? $row->update($attrs)
            : ProductionRecord::create($attrs + [
                'date' => $date->toDateString(),
                'shift' => $shift,
                'machine_id' => $machine->id,
                'start_time' => $start . ':00',
            ]);
    }

    /** Maks. 1 downtime selesai per mesin per hari, pada jam tetap di dalam shift 1 (tidak bisa overlap). */
    private function seedFinishedDowntime(Carbon $date, Machine $machine, MasterProduction $master, int $daysAgo): void
    {
        $hash = abs(crc32("dt|{$machine->name}|{$date->toDateString()}"));

        if ($hash % 3 === 0 || ! $master->pic_shift1) {
            return; // ~1/3 hari tanpa downtime
        }

        $code = self::DOWNTIME_CODES[$hash % count(self::DOWNTIME_CODES)];
        $category = DowntimeCategory::find($code);

        if (! $category || ! $category->is_downtime) {
            return;
        }

        $startAt = $date->copy()->setTime(8 + ($hash % 5), ($hash >> 3) % 2 ? 30 : 0); // 08:00-12:30
        $endAt = $startAt->copy()->addMinutes(15 + ($hash % 6) * 10);                    // 15-65 menit

        // Hari ini: hanya yang sudah selesai sebelum sekarang.
        if ($endAt->greaterThan(now())) {
            return;
        }

        DowntimeEvent::updateOrCreate(
            ['machine_id' => $machine->id, 'start_at' => $startAt],
            [
                'category_code' => $code,
                'end_at' => $endAt,
                'pic' => $master->pic_shift1,
                'notes' => 'Data contoh',
            ]
        );
    }

    /**
     * Satu downtime AKTIF (end_at null) hari ini agar status "downtime" tampil di dashboard.
     * Hanya dibuat saat sedang jam shift, dan tidak dibuat bila mesin itu sudah punya event aktif.
     */
    private function seedActiveDowntime(Carbon $now, $machines, $masters): void
    {
        $shift = \App\Support\Shift::forDateTime($now);
        $machine = $machines['HBF-823'] ?? $machines->first();
        $master = $masters[$machine->name] ?? null;

        if (! $shift || ! $master) {
            return;
        }

        $pic = $shift === 1 ? $master->pic_shift1 : $master->pic_shift2;
        $startAt = $now->copy()->subMinutes(20)->startOfMinute();
        $shiftStart = $now->copy()->setTimeFromTimeString(config("yahata.shifts.{$shift}.start"));

        if (! $pic || $startAt->lessThan($shiftStart)) {
            return;
        }

        $busy = DowntimeEvent::where('machine_id', $machine->id)
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>', $startAt))
            ->exists();

        if ($busy) {
            return;
        }

        DowntimeEvent::create([
            'machine_id' => $machine->id,
            'category_code' => 5, // Trouble Machine
            'start_at' => $startAt,
            'end_at' => null,
            'pic' => $pic,
            'notes' => 'Data contoh: downtime sedang berjalan',
        ]);
    }
}
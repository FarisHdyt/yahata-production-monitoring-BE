<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\MasterProduction;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Master Production 14 mesin + daftar part (partOptions), sama dengan DEFAULT_MASTER_DATA di FE.
 * Data ada di database/seeders/data/master_productions.json.
 *
 * Idempotent, tapi updateOrCreate menimpa master yang sudah diedit lewat aplikasi.
 * Karena itu untuk mesin yang SUDAH punya master, seeder ini tidak menimpanya (firstOrCreate).
 * Untuk mereset ke default: pakai tombol "Reset ke Default" di FE atau hapus barisnya dulu.
 */
class MasterProductionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/master_productions.json');
        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            throw new RuntimeException("File master tidak valid: {$path}");
        }

        foreach ($rows as $row) {
            $machine = Machine::where('name', $row['machine'])->first();

            if (! $machine) {
                $this->command?->warn("Mesin {$row['machine']} tidak ada di tabel machines, dilewati.");
                continue;
            }

            MasterProduction::firstOrCreate(
                ['machine_id' => $machine->id],
                [
                    'part_number' => $row['partNumber'],
                    'customer' => $row['customer'] ?: null,
                    'size_part' => $row['sizePart'] ?: null,
                    'rpm' => $row['rpm'],
                    'pcs_per_revolution' => max(1, (float) $row['pcsPerRevolution']),
                    'pic_shift1' => $row['picShift1'] ?: null,
                    'pic_shift2' => $row['picShift2'] ?: null,
                    'part_options' => $row['partOptions'],
                ]
            );
        }
    }
}
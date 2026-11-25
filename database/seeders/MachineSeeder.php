<?php

namespace Database\Seeders;

use App\Models\Machine;
use Illuminate\Database\Seeder;

/** Master 14 mesin area Forming (sumber: config/yahata.php). Aman dijalankan berulang & di production. */
class MachineSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('yahata.machines') as $name) {
            Machine::firstOrCreate(['name' => $name]);
        }
    }
}
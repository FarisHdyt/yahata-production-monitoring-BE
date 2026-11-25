<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data referensi: aman untuk production & idempotent.
        $this->call([
            MachineSeeder::class,
            DowntimeCategorySeeder::class,
            AdminUserSeeder::class,
            MasterProductionSeeder::class,
        ]);

        // Data contoh: default hanya di APP_ENV=local. Paksa dengan SEED_DEMO=true / matikan dengan SEED_DEMO=false.
        if (filter_var(env('SEED_DEMO', app()->environment('local')), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoSeeder::class);
        }
    }
}
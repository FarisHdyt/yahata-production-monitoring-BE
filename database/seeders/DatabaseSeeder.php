<?php

namespace Database\Seeders;

use App\Models\DowntimeCategory;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('yahata.machines') as $name) {
            Machine::firstOrCreate(['name' => $name]);
        }

        foreach (config('yahata.categories') as $category) {
            DowntimeCategory::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'is_downtime' => $category['is_downtime']]
            );
        }

        // Admin pertama. NIK sengaja null: baru diisi saat integrasi super app.
        // Password hanya dipakai saat akun BARU dibuat (firstOrCreate tidak menimpa akun yang sudah ada).
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'nik' => null,
                'name' => 'Administrator',
                'role' => 'admin',
                'is_active' => true,
                'password' => env('SEED_ADMIN_PASSWORD', 'Admin12345'), // di-hash oleh cast
            ]
        );
    }
}
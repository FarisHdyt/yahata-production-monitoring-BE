<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Admin pertama. NIK sengaja null (diisi saat integrasi super app).
 * firstOrCreate: akun yang sudah ada TIDAK ditimpa (password tidak ikut ter-reset).
 * Password plain karena model User punya cast 'hashed' (jangan Hash::make -> double hash).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'nik' => null,
                'name' => 'Administrator',
                'role' => 'admin',
                'is_active' => true,
                'password' => env('SEED_ADMIN_PASSWORD', 'Admin12345'),
            ]
        );
    }
}
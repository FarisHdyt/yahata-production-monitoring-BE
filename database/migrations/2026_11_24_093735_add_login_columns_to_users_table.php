<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Master ID untuk integrasi super app. Diisi belakangan, jadi nullable.
            $table->string('nik', 30)->nullable();
            $table->string('username', 50)->nullable(); // dijadikan NOT NULL di bawah
            $table->string('role', 20)->default('operator'); // operator | admin
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });

        // Kalau sudah ada baris user lama, beri username sementara supaya bisa dijadikan NOT NULL.
        DB::table('users')->whereNull('username')->update(['username' => DB::raw("'user' || id")]);

        Schema::table('users', function (Blueprint $table) {
            // Unique di PostgreSQL mengizinkan banyak NULL, jadi NIK kosong tidak bentrok.
            $table->unique('nik');
            $table->unique('username');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        }

        // Kolom bawaan Laravel yang tidak dipakai (login berbasis token, tanpa verifikasi email)
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'remember_token']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropUnique(['username']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nik', 'username', 'role', 'is_active', 'last_login_at']);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
        });
        // Catatan: kolom email tidak dikembalikan ke NOT NULL karena bisa gagal bila ada baris tanpa email.
    }
};
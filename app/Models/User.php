<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    // makin besar angka, makin tinggi akses
    public const ROLES = ['operator' => 1, 'admin' => 2];

    protected $fillable = [
        'nik',
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed', // otomatis di-hash saat diisi; jangan Hash::make() lagi
    ];

    public function hasMinimumRole(string $role): bool
    {
        return (self::ROLES[$this->role] ?? 0) >= (self::ROLES[$role] ?? PHP_INT_MAX);
    }
}
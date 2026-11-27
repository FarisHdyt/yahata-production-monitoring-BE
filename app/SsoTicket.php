<?php

namespace App;

use Illuminate\Support\Facades\Cache;

/**
 * Pemeriksa tiket SSO dari Superapps YFI.
 *
 * Format: tiket = B . "." . hex(HMAC-SHA256(B, SSO_SECRET))
 *         B     = base64url("<nik>|<exp>") tanpa padding, exp = detik unix UTC
 */
class SsoTicket
{
    const MAX_TTL = 120; // tiket yang berlaku lebih dari 2 menit ke depan ditolak

    /** Pembuat tiket: hanya untuk uji (php artisan sso:tiket). */
    public static function make(string $nik, int $ttl = 60): string
    {
        $b = rtrim(strtr(base64_encode($nik . '|' . (time() + $ttl)), '+/', '-_'), '=');

        return $b . '.' . hash_hmac('sha256', $b, self::secret());
    }

    /** NIK bila tiket sah, belum kedaluwarsa, dan belum pernah dipakai. Selain itu null. */
    public static function nik(string $tiket): ?string
    {
        [$b, $sig] = explode('.', $tiket, 2) + [1 => ''];

        if (! hash_equals(hash_hmac('sha256', $b, self::secret()), $sig)) {
            return null;
        }

        [$nik, $exp] = explode('|', (string) base64_decode(strtr($b, '-_', '+/')), 2) + [1 => ''];
        $nik = trim($nik);

        if ($nik === '' || ! ctype_digit($exp)) {
            return null;
        }

        $left = (int) $exp - time();

        if ($left < 0 || $left > self::MAX_TTL) {
            return null;
        }

        // Sekali pakai: tanda tangan dicatat sampai tiket kedaluwarsa.
        return Cache::add("sso:$sig", true, $left + 1) ? $nik : null;
    }

    private static function secret(): string
    {
        $k = (string) config('services.sso.secret');
        abort_if(strlen($k) < 32, 503, 'SSO belum dikonfigurasi (SSO_SECRET di .env, minimal 32 karakter).');

        return $k;
    }
}
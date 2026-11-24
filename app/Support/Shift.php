<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Shift
{
    public static function all(): array
    {
        return config('yahata.shifts');
    }

    public static function get(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Terima 1, "1", "shift1", "Shift 2", "all", null -> int|null */
    public static function normalize(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
        } elseif (preg_match('/(\d+)/', (string) $value, $m)) {
            $id = (int) $m[1];
        } else {
            return null;
        }

        return isset(self::all()[$id]) ? $id : null;
    }

    public static function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $h * 60 + $m;
    }

    /** Shift ditentukan dari jam (waktu lokal app). null = di luar jadwal. */
    public static function forDateTime(CarbonInterface $dt): ?int
    {
        $minute = $dt->hour * 60 + $dt->minute;

        foreach (self::all() as $id => $shift) {
            if ($minute >= self::toMinutes($shift['start']) && $minute < self::toMinutes($shift['end'])) {
                return $id;
            }
        }

        return null;
    }

    public static function name(?int $id): string
    {
        return $id ? (self::get($id)['name'] ?? 'Di luar shift') : 'Di luar shift';
    }

    public static function schedule(?int $id): string
    {
        $s = $id ? self::get($id) : null;

        return $s ? "{$s['start']} - {$s['end']}" : '-';
    }
}
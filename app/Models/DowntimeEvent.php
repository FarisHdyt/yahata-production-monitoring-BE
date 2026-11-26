<?php

namespace App\Models;

use App\Support\Shift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DowntimeEvent extends Model
{
    protected $fillable = ['machine_id', 'category_code', 'start_at', 'end_at', 'pic', 'notes'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'shift' => 'integer',
    ];

    protected static function booted(): void
    {
        // Shift ditentukan dari waktu mulai downtime (sama seperti frontend).
        static::saving(function (self $event) {
            $event->shift = Shift::forDateTime($event->start_at);
        });
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DowntimeCategory::class, 'category_code', 'code');
    }

    public function isActive(): bool
    {
        return $this->end_at === null;
    }

    public function durationMinutes(?CarbonInterface $now = null): int
    {
        $end = $this->end_at ?? ($now ?? now());

        return intdiv(max(0, $end->getTimestamp() - $this->start_at->getTimestamp()), 60);
    }

    /** Kategori 1 (Running Production) bukan downtime. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('category_code', '<>', 1);
    }

    /** Downtime yang sedang berlangsung pada waktu $now. */
    public function scopeActive(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where('start_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>', $now));
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            // Rentang waktu (bukan whereDate) supaya index start_at bisa dipakai.
            ->when($f['date'] ?? null, fn ($q, $v) => $q
                ->where('start_at', '>=', $v . ' 00:00:00')
                ->where('start_at', '<=', $v . ' 23:59:59'))
            ->when($f['startDate'] ?? null, fn ($q, $v) => $q->where('start_at', '>=', $v . ' 00:00:00'))
            ->when($f['endDate'] ?? null, fn ($q, $v) => $q->where('start_at', '<=', $v . ' 23:59:59'))
            ->when($f['shift'] ?? null, fn ($q, $v) => $q->where('shift', $v))
            ->when($f['machine'] ?? null, fn ($q, $v) => $q->whereHas('machine', fn ($m) => $m->where('name', $v)))
            ->when(($f['status'] ?? null) === 'active', fn ($q) => $q->whereNull('end_at'))
            ->when(($f['status'] ?? null) === 'finished', fn ($q) => $q->whereNotNull('end_at'));
    }
}
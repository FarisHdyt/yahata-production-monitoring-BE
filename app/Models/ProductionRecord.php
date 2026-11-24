<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecord extends Model
{
    protected $fillable = [
        'date', 'shift', 'machine_id', 'part_number',
        'start_time', 'end_time', 'output', 'pic', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'shift' => 'integer',
        'output' => 'integer',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['date'] ?? null, fn ($q, $v) => $q->whereDate('date', $v))
            ->when($f['startDate'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['endDate'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($f['shift'] ?? null, fn ($q, $v) => $q->where('shift', $v))
            ->when($f['partNumber'] ?? null, fn ($q, $v) => $q->where('part_number', $v))
            ->when($f['machine'] ?? null, fn ($q, $v) => $q->whereHas('machine', fn ($m) => $m->where('name', $v)));
    }
}
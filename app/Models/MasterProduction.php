<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProduction extends Model
{
    protected $fillable = [
        'machine_id',
        'part_number',
        'customer',
        'size_part',
        'rpm',
        'pcs_per_revolution',
        'notes',
        'pic_shift1',
        'pic_shift2',
        'part_options',
    ];

    protected $casts = [
        'rpm' => 'float',
        'pcs_per_revolution' => 'float',
        'part_options' => 'array',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
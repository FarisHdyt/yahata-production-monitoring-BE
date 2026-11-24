<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProduction extends Model
{
    protected $fillable = ['machine_id', 'part_number', 'rpm', 'pcs_per_revolution', 'notes'];

    protected $casts = [
        'rpm' => 'float',
        'pcs_per_revolution' => 'float',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
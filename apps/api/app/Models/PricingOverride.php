<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PricingOverride extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'customer_id', 'project_id', 'activity_type_id',
        'hourly_rate_minor', 'currency', 'effective_from', 'effective_until', 'note',
    ];

    protected $casts = [
        'hourly_rate_minor' => 'integer',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];
}

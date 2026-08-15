<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BillingRateSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'activity_session_id', 'base_rate_minor', 'customer_multiplier',
        'project_multiplier', 'effective_rate_minor', 'billable_seconds',
        'amount_minor', 'currency', 'resolution_source', 'pricing_override_id',
    ];

    protected $casts = [
        'base_rate_minor' => 'integer',
        'effective_rate_minor' => 'integer',
        'billable_seconds' => 'integer',
        'amount_minor' => 'integer',
        'customer_multiplier' => 'decimal:4',
        'project_multiplier' => 'decimal:4',
    ];
}

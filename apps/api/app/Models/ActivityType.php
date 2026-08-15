<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'code', 'name', 'is_billable_default', 'base_hourly_rate_minor',
        'currency', 'is_active', 'sort_order', 'version',
    ];

    protected $casts = [
        'is_billable_default' => 'boolean',
        'is_active' => 'boolean',
        'base_hourly_rate_minor' => 'integer',
        'version' => 'integer',
    ];
}

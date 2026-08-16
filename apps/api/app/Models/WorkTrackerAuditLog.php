<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkTrackerAuditLog extends Model
{
    use HasUuids;

    /**
     * Laravel would infer "work_tracker_audit_logs" from the model name, while the
     * original WorkTracker migration intentionally created "worktracker_audit_logs".
     * Keep the table name explicit so Audit works on existing installations without
     * renaming or backfilling historical records.
     */
    protected $table = 'worktracker_audit_logs';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'before_json',
        'after_json',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];
}

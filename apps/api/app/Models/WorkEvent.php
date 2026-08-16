<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'device_id', 'project_id', 'projection_date', 'timezone',
        'event_kind', 'context_key', 'started_at', 'ended_at', 'direct_seconds',
        'bridge_seconds', 'credited_seconds', 'segment_count', 'bridge_count',
        'applications', 'projection_version', 'calculated_at',
    ];

    protected $casts = [
        'projection_date' => 'date',
        'started_at' => 'immutable_datetime',
        'ended_at' => 'immutable_datetime',
        'calculated_at' => 'immutable_datetime',
        'direct_seconds' => 'integer',
        'bridge_seconds' => 'integer',
        'credited_seconds' => 'integer',
        'segment_count' => 'integer',
        'bridge_count' => 'integer',
        'applications' => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function segments(): HasMany { return $this->hasMany(WorkEventSegment::class); }
    public function bridges(): HasMany { return $this->hasMany(ContinuityBridge::class); }
}

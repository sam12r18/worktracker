<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkEventSegment extends Model
{
    protected $fillable = [
        'work_event_id', 'activity_session_id', 'position', 'started_at', 'ended_at', 'duration_seconds',
    ];

    protected $casts = [
        'position' => 'integer',
        'started_at' => 'immutable_datetime',
        'ended_at' => 'immutable_datetime',
        'duration_seconds' => 'integer',
    ];

    public function workEvent(): BelongsTo { return $this->belongsTo(WorkEvent::class); }
    public function activitySession(): BelongsTo { return $this->belongsTo(ActivitySession::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuityBridge extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'work_event_id', 'user_id', 'device_id', 'anchor_project_id', 'projection_date',
        'started_at', 'ended_at', 'duration_seconds', 'interrupted_project_ids', 'reason', 'projection_version',
    ];

    protected $casts = [
        'projection_date' => 'date',
        'started_at' => 'immutable_datetime',
        'ended_at' => 'immutable_datetime',
        'duration_seconds' => 'integer',
        'interrupted_project_ids' => 'array',
    ];

    public function workEvent(): BelongsTo { return $this->belongsTo(WorkEvent::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function anchorProject(): BelongsTo { return $this->belongsTo(Project::class, 'anchor_project_id'); }
}

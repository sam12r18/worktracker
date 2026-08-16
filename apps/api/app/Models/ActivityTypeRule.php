<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityTypeRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'project_id', 'activity_type_id', 'rule_type', 'operator', 'pattern',
        'weight', 'priority', 'confidence', 'is_enabled', 'version',
    ];

    protected $casts = [
        'weight' => 'integer',
        'priority' => 'integer',
        'confidence' => 'float',
        'is_enabled' => 'boolean',
        'version' => 'integer',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function activityType(): BelongsTo { return $this->belongsTo(ActivityType::class); }
}

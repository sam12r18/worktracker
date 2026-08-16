<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySession extends Model
{
    use HasUuids;
    public $incrementing=false; protected $keyType='string';
    protected $fillable=['device_id','project_id','activity_type_id','activity_type_confidence','activity_type_source','activity_type_reason','is_billable','task_id','source','process_name','executable_path','window_title','classification_confidence','classification_reason','started_at','ended_at','duration_seconds','idle_seconds','note','version','created_at_device','updated_at_device'];
    protected $casts=['started_at'=>'datetime','ended_at'=>'datetime','created_at_device'=>'datetime','updated_at_device'=>'datetime','is_billable'=>'boolean','classification_confidence'=>'float','activity_type_confidence'=>'float','version'=>'integer'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function activityType(): BelongsTo { return $this->belongsTo(ActivityType::class); }
}

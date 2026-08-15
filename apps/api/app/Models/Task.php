<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasUlids;

    protected $fillable = [
        'project_id','parent_id','title','description','status','priority','due_at',
        'started_at','completed_at','estimated_minutes','sort_order'
    ];

    protected $casts = [
        'due_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime',
        'estimated_minutes'=>'integer','sort_order'=>'integer'
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Task::class, 'parent_id'); }
}

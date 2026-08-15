<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRule extends Model
{
    use HasUuids;
    protected $fillable = ['rule_type','operator','pattern','weight','priority','is_enabled','version'];
    protected $casts = ['is_enabled'=>'boolean','weight'=>'integer','priority'=>'integer','version'=>'integer'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}

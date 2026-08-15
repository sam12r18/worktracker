<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $fillable = [
        'parent_id','name','code','status','color','is_archived','version',
        'customer_id','rate_multiplier','is_billable_default',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'version' => 'integer',
        'rate_multiplier' => 'decimal:4',
        'is_billable_default' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Project::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Project::class, 'parent_id'); }
    public function rules(): HasMany { return $this->hasMany(ProjectRule::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','name','operator_label','fingerprint_hash','platform','app_version','last_seen_at','last_sync_started_at','last_sync_succeeded_at','last_sync_error','last_sync_pushed','last_sync_pulled'];
    protected $casts = ['last_seen_at'=>'datetime','last_sync_started_at'=>'datetime','last_sync_succeeded_at'=>'datetime','revoked_at'=>'datetime','last_sync_pushed'=>'integer','last_sync_pulled'=>'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

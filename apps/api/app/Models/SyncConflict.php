<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncConflict extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id','user_id','device_id','entity_type','entity_id','client_version','server_version',
        'client_payload','server_payload','reason','status','resolution','resolved_by_user_id',
        'resolved_at','acknowledged_at'
    ];
    protected $casts = [
        'client_payload'=>'array','server_payload'=>'array','client_version'=>'integer','server_version'=>'integer',
        'resolved_at'=>'datetime','acknowledged_at'=>'datetime'
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by_user_id'); }
}

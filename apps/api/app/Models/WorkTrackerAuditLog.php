<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class WorkTrackerAuditLog extends Model {
 use HasUuids; protected $fillable=['user_id','entity_type','entity_id','action','before_json','after_json','reason','ip_address','user_agent'];
 protected $casts=['before_json'=>'array','after_json'=>'array'];
}

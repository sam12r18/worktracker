<?php
namespace App\Services;
use App\Models\WorkTrackerAuditLog; use Illuminate\Http\Request;
final class WorkTrackerAuditService {
 public function record(Request $request,string $entityType,string $entityId,string $action,?array $before,?array $after,?string $reason=null):WorkTrackerAuditLog {
  return WorkTrackerAuditLog::create(['user_id'=>$request->user()->getKey(),'entity_type'=>$entityType,'entity_id'=>$entityId,'action'=>$action,'before_json'=>$before,'after_json'=>$after,'reason'=>$reason,'ip_address'=>$request->ip(),'user_agent'=>mb_substr((string)$request->userAgent(),0,500)]);
 }
}

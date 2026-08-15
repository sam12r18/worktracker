<?php
namespace App\Services;
use App\Models\ActivitySession; use Carbon\CarbonImmutable; use Illuminate\Support\Collection;
final class CentralReportingService {
 public function __construct(private readonly TimeAccountingService $accounting) {}
 public function daily(int|string $userId, CarbonImmutable $dayStart, ?string $deviceId=null):array { return $this->range($userId,$dayStart,$dayStart->addDay(),$deviceId); }
 public function range(int|string $userId, CarbonImmutable $from, CarbonImmutable $to, ?string $deviceId=null):array {
  $q=$this->baseQuery($userId,$from,$to); if($deviceId)$q->where('device_id',$deviceId);
  $sessions=$q->with(['project:id,name,code','device:id,name,operator_label','activityType:id,name,code'])->get();
  $days=collect(); for($cursor=$from->startOfDay();$cursor->lessThan($to);$cursor=$cursor->addDay()){
   $start=$cursor->lessThan($from)?$from:$cursor; $end=$cursor->addDay()->greaterThan($to)?$to:$cursor->addDay();
   $rows=$sessions->filter(fn($s)=>CarbonImmutable::parse($s->ended_at)->greaterThan($start)&&CarbonImmutable::parse($s->started_at)->lessThan($end))->values();
   $days->push(['date'=>$cursor->toDateString(),'sessions_count'=>$rows->count()]+$this->accounting->summarize($rows,$start,$end));
  }
  return ['range'=>['from'=>$from->toISOString(),'to'=>$to->toISOString()],'summary'=>$this->accounting->summarize($sessions,$from,$to),'projects'=>$this->groupByProject($sessions,$from,$to),'devices'=>$this->groupByDevice($sessions,$from,$to),'sources'=>$this->groupBySource($sessions,$from,$to),'activity_types'=>$this->groupByActivityType($sessions,$from,$to),'unknown'=>$this->groupUnknown($sessions,$from,$to),'days'=>$days,'sessions_count'=>$sessions->count()];
 }
 public function project(int|string $userId,string $projectId,CarbonImmutable $from,CarbonImmutable $to):array {
  $report=$this->range($userId,$from,$to); $sessions=$this->baseQuery($userId,$from,$to)->where('project_id',$projectId)->with(['project:id,name,code','device:id,name,operator_label','activityType:id,name,code'])->get();
  return ['project_id'=>$projectId,'range'=>$report['range'],'summary'=>$this->accounting->summarize($sessions,$from,$to),'devices'=>$this->groupByDevice($sessions,$from,$to),'sources'=>$this->groupBySource($sessions,$from,$to),'activity_types'=>$this->groupByActivityType($sessions,$from,$to),'days'=>$this->daysFor($sessions,$from,$to),'sessions_count'=>$sessions->count()];
 }
 public function sessions(int|string $userId,CarbonImmutable $from,CarbonImmutable $to,?string $projectId=null):Collection { $q=$this->baseQuery($userId,$from,$to); if($projectId)$q->where('project_id',$projectId); return $q->with(['project:id,name,code','device:id,name,operator_label','activityType:id,name,code'])->get(); }
 private function daysFor(Collection $sessions,CarbonImmutable $from,CarbonImmutable $to):Collection { $out=collect(); for($c=$from->startOfDay();$c->lessThan($to);$c=$c->addDay()){ $s=$c->lessThan($from)?$from:$c;$e=$c->addDay()->greaterThan($to)?$to:$c->addDay();$r=$sessions->filter(fn($x)=>CarbonImmutable::parse($x->ended_at)->greaterThan($s)&&CarbonImmutable::parse($x->started_at)->lessThan($e))->values();$out->push(['date'=>$c->toDateString(),'sessions_count'=>$r->count()]+$this->accounting->summarize($r,$s,$e)); } return $out; }
 private function baseQuery(int|string $userId,CarbonImmutable $from,CarbonImmutable $to){return ActivitySession::query()->where('user_id',$userId)->where('ended_at','>',$from)->where('started_at','<',$to)->orderBy('started_at');}
 private function groupByProject(Collection $s,CarbonImmutable $f,CarbonImmutable $t):Collection{return $s->groupBy(fn($x)=>$x->project_id?:'__unknown__')->map(function(Collection $r,string $k)use($f,$t){$x=$r->first();return ['project_id'=>$k==='__unknown__'?null:$k,'name'=>$x->project?->name??'تشخیص‌داده‌نشده','code'=>$x->project?->code,'sessions_count'=>$r->count()]+$this->accounting->summarize($r,$f,$t);})->sortByDesc('effort_seconds')->values();}
 private function groupByDevice(Collection $s,CarbonImmutable $f,CarbonImmutable $t):Collection{return $s->groupBy('device_id')->map(function(Collection $r,string $id)use($f,$t){$d=$r->first()->device;return ['device_id'=>$id,'name'=>$d?->name??$id,'operator_label'=>$d?->operator_label,'sessions_count'=>$r->count()]+$this->accounting->summarize($r,$f,$t);})->sortByDesc('effort_seconds')->values();}
 private function groupBySource(Collection $s,CarbonImmutable $f,CarbonImmutable $t):Collection{return $s->groupBy('source')->map(fn(Collection $r,string $x)=>['source'=>$x,'sessions_count'=>$r->count()]+$this->accounting->summarize($r,$f,$t))->sortByDesc('effort_seconds')->values();}
 private function groupByActivityType(Collection $s,CarbonImmutable $f,CarbonImmutable $t):Collection{return $s->groupBy(fn($x)=>$x->activity_type_id?:'__none__')->map(function(Collection $r,string $id)use($f,$t){$x=$r->first();return ['activity_type_id'=>$id==='__none__'?null:$id,'name'=>$x->activityType?->name??'بدون نوع','sessions_count'=>$r->count()]+$this->accounting->summarize($r,$f,$t);})->sortByDesc('effort_seconds')->values();}
 private function groupUnknown(Collection $s,CarbonImmutable $f,CarbonImmutable $t):array{$u=$s->whereNull('project_id')->values();return ['sessions_count'=>$u->count()]+$this->accounting->summarize($u,$f,$t);}
}

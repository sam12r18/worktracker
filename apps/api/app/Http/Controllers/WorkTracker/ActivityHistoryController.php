<?php
namespace App\Http\Controllers\WorkTracker;
use App\Http\Controllers\Controller; use App\Models\{ActivitySession,ActivityType,BillingRateSnapshot,Project,WorkTrackerAuditLog}; use App\Services\WorkTrackerAuditService; use Carbon\CarbonImmutable; use Illuminate\Http\{Request,RedirectResponse}; use Illuminate\Support\Facades\DB; use Illuminate\Validation\Rule; use Illuminate\View\View;
class ActivityHistoryController extends Controller {
 public function index(Request $request):View {
  $uid=$request->user()->getKey(); $tz=(string)($request->query('timezone')?:config('app.timezone','UTC')); $date=(string)($request->query('date')?:now($tz)->toDateString()); $start=CarbonImmutable::parse($date,$tz)->startOfDay();$end=$start->addDay();
  $q=ActivitySession::query()->where('user_id',$uid)->where('ended_at','>',$start)->where('started_at','<',$end)->with(['project:id,name','activityType:id,name','device:id,name,operator_label'])->orderBy('started_at');
  if($request->filled('project_id'))$q->where('project_id',$request->query('project_id'));
  $activities=$q->get(); $billed=BillingRateSnapshot::query()->whereIn('activity_session_id',$activities->pluck('id'))->pluck('activity_session_id')->flip();
  return view('worktracker.activities.index',['activities'=>$activities,'billed'=>$billed,'projects'=>Project::where('user_id',$uid)->where('is_archived',false)->orderBy('name')->get(),'activityTypes'=>ActivityType::where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$uid))->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),'date'=>$date,'timezone'=>$tz]);
 }
 public function update(Request $request,ActivitySession $activity,WorkTrackerAuditService $audit):RedirectResponse {
  abort_unless((string)$activity->user_id===(string)$request->user()->getKey(),404); abort_if(BillingRateSnapshot::where('activity_session_id',$activity->id)->exists(),409,'فعالیت داخل فاکتور نهایی است و قابل ویرایش مستقیم نیست.');
  $data=$request->validate(['project_id'=>['nullable','uuid',Rule::exists('projects','id')->where('user_id',$request->user()->getKey())],'activity_type_id'=>['nullable','uuid',Rule::exists('activity_types','id')->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$request->user()->getKey()))],'started_at'=>['required','date'],'ended_at'=>['required','date','after:started_at'],'is_billable'=>['nullable','in:default,yes,no'],'note'=>['nullable','string','max:20000'],'reason'=>['required','string','max:1000'],'timezone'=>['required','timezone']]);
  $before=$activity->only(['project_id','activity_type_id','started_at','ended_at','duration_seconds','is_billable','note','version']);
  DB::transaction(function()use($activity,$data){$start=CarbonImmutable::parse($data['started_at'],$data['timezone'])->utc();$end=CarbonImmutable::parse($data['ended_at'],$data['timezone'])->utc();$activity->project_id=$data['project_id']?:null;$activity->activity_type_id=$data['activity_type_id']?:null;$activity->started_at=$start;$activity->ended_at=$end;$activity->duration_seconds=max(1,$start->diffInSeconds($end));$activity->is_billable=match($data['is_billable']??'default'){'yes'=>true,'no'=>false,default=>null};$activity->note=$data['note']?:null;$activity->version=(int)$activity->version+1;$activity->save();});
  $audit->record($request,'activity_session',(string)$activity->id,'historical_update',$before,$activity->fresh()->only(['project_id','activity_type_id','started_at','ended_at','duration_seconds','is_billable','note','version']),$data['reason']);
  return back()->with('status','فعالیت ویرایش شد و Audit Log ثبت شد.');
 }
 public function audit(Request $request):View { $logs=WorkTrackerAuditLog::where('user_id',$request->user()->getKey())->latest()->paginate(100); return view('worktracker.activities.audit',['logs'=>$logs]); }
}

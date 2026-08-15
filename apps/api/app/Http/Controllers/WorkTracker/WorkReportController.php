<?php
namespace App\Http\Controllers\WorkTracker;
use App\Http\Controllers\Controller; use App\Models\Project; use App\Services\CentralReportingService; use Carbon\CarbonImmutable; use Illuminate\Http\Request; use Illuminate\View\View;
class WorkReportController extends Controller {
 public function index(Request $request,CentralReportingService $reports):View {
  $uid=$request->user()->getKey();$tz=(string)($request->query('timezone')?:config('app.timezone','UTC'));$preset=(string)($request->query('preset')?:'week');$today=CarbonImmutable::now($tz)->startOfDay();
  [$from,$to]=match($preset){'day'=>[$today,$today->addDay()],'month'=>[$today->startOfMonth(),$today->addMonth()->startOfMonth()],'custom'=>[CarbonImmutable::parse((string)$request->query('from',$today->toDateString()),$tz)->startOfDay(),CarbonImmutable::parse((string)$request->query('to',$today->toDateString()),$tz)->addDay()->startOfDay()],default=>[$today->startOfWeek(),$today->startOfWeek()->addWeek()]};
  $projectId=$request->query('project_id');$report=$projectId?$reports->project($uid,(string)$projectId,$from,$to):$reports->range($uid,$from,$to);
  $sessions=$reports->sessions($uid,$from,$to,$projectId?:null);
  return view('worktracker.reports.index',['report'=>$report,'sessions'=>$sessions,'projects'=>Project::where('user_id',$uid)->where('is_archived',false)->orderBy('name')->get(),'preset'=>$preset,'from'=>$from,'to'=>$to,'timezone'=>$tz,'projectId'=>$projectId]);
 }
}

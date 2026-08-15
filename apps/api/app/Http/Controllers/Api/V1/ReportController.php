<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CentralReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request, CentralReportingService $reports): JsonResponse
    {
        $data=$request->validate([
            'date'=>['nullable','date'],
            'timezone'=>['nullable','timezone'],
            'device_id'=>['nullable','uuid'],
        ]);
        $tz=$data['timezone'] ?? config('app.timezone', 'UTC');
        $day=isset($data['date']) ? CarbonImmutable::parse($data['date'],$tz)->startOfDay() : CarbonImmutable::now($tz)->startOfDay();
        return response()->json(['data'=>$reports->daily($request->user()->getKey(),$day,$data['device_id'] ?? null)]);
    }

    public function project(Request $request, Project $project, CentralReportingService $reports): JsonResponse
    {
        abort_unless((string)$project->user_id === (string)$request->user()->getKey(),404);
        $data=$request->validate([
            'from'=>['required','date'],
            'to'=>['required','date','after:from'],
            'timezone'=>['nullable','timezone'],
        ]);
        $tz=$data['timezone'] ?? config('app.timezone','UTC');
        $from=CarbonImmutable::parse($data['from'],$tz);
        $to=CarbonImmutable::parse($data['to'],$tz);
        return response()->json(['data'=>$reports->project($request->user()->getKey(),(string)$project->getKey(),$from,$to)]);
    }
}

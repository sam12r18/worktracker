<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\Device;
use App\Models\SyncConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $userId=$request->user()->getKey();
        $devices=Device::query()->where('user_id',$userId)->orderByDesc('last_seen_at')->get();
        $openConflicts=SyncConflict::query()->where('user_id',$userId)->where('status','open')->count();
        $lastActivity=ActivitySession::query()->where('user_id',$userId)->max('ended_at');
        return response()->json(['data'=>[
            'devices'=>$devices,
            'devices_count'=>$devices->count(),
            'active_devices_24h'=>$devices->filter(fn($d)=>$d->last_seen_at?->greaterThan(now()->subDay()))->count(),
            'revoked_devices'=>$devices->whereNotNull('revoked_at')->count(),
            'open_conflicts'=>$openConflicts,
            'last_activity_at'=>$lastActivity,
        ]]);
    }
}

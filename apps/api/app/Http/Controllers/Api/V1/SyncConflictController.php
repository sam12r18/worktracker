<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SyncConflict;
use App\Services\SyncConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncConflictController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data=$request->validate(['status'=>['nullable','in:open,resolved'],'device_id'=>['nullable','uuid']]);
        $q=SyncConflict::query()->where('user_id',$request->user()->getKey())->with('device:id,name,operator_label')->latest();
        if(isset($data['status']))$q->where('status',$data['status']);
        if(isset($data['device_id']))$q->where('device_id',$data['device_id']);
        return response()->json($q->paginate(100));
    }

    public function show(Request $request, SyncConflict $syncConflict): JsonResponse
    {
        abort_unless((string)$syncConflict->user_id === (string)$request->user()->getKey(),404);
        return response()->json(['data'=>$syncConflict->load('device:id,name,operator_label')]);
    }

    public function resolve(Request $request, SyncConflict $syncConflict, SyncConflictService $service): JsonResponse
    {
        abort_unless((string)$syncConflict->user_id === (string)$request->user()->getKey(),404);
        $data=$request->validate(['resolution'=>['required','in:keep_server,accept_client']]);
        return response()->json(['data'=>$service->resolve($syncConflict,$data['resolution'],$request->user()->getKey())]);
    }
}

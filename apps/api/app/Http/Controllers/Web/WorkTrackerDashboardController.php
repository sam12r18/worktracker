<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Project;
use App\Models\SyncConflict;
use App\Services\CentralReportingService;
use App\Services\SyncConflictService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkTrackerDashboardController extends Controller
{
    public function dashboard(Request $request, CentralReportingService $reports): View
    {
        $tz=(string)($request->query('timezone') ?: config('worktracker.display_timezone','Asia/Tehran'));
        $date=(string)($request->query('date') ?: CarbonImmutable::now($tz)->toDateString());
        $day=CarbonImmutable::parse($date,$tz)->startOfDay();
        $userId=$request->user()->getKey();
        return view('worktracker.dashboard',[
            'report'=>$reports->daily($userId,$day),
            'date'=>$date,'timezone'=>$tz,
            'devices'=>Device::query()->where('user_id',$userId)->latest('last_seen_at')->get(),
            'projects'=>Project::query()->where('user_id',$userId)->where('is_archived',false)->orderBy('name')->get(),
            'openConflicts'=>SyncConflict::query()->where('user_id',$userId)->where('status','open')->latest()->limit(20)->get(),
            'accessTokens'=>$request->user()->tokens()->where('name','like','worktracker:%')->latest()->get(),
        ]);
    }

    public function conflicts(Request $request): View
    {
        $rows=SyncConflict::query()->where('user_id',$request->user()->getKey())->with('device:id,name,operator_label')->latest()->paginate(50);
        return view('worktracker.conflicts',['conflicts'=>$rows]);
    }

    public function resolveConflict(Request $request, SyncConflict $syncConflict, SyncConflictService $service): RedirectResponse
    {
        abort_unless((string)$syncConflict->user_id === (string)$request->user()->getKey(),404);
        $data=$request->validate(['resolution'=>['required','in:keep_server,accept_client']]);
        $service->resolve($syncConflict,$data['resolution'],$request->user()->getKey());
        return back()->with('status','تعارض ثبت شد و در Sync بعدی به دستگاه اعلام می‌شود.');
    }

    public function updateDevice(Request $request, Device $device): RedirectResponse
    {
        abort_unless((string)$device->user_id === (string)$request->user()->getKey(),404);
        $data=$request->validate(['name'=>['required','string','max:120'],'operator_label'=>['nullable','string','max:120']]);
        $device->update($data);
        return back()->with('status','مشخصات دستگاه ذخیره شد.');
    }

    public function revokeDevice(Request $request, Device $device): RedirectResponse
    {
        abort_unless((string)$device->user_id === (string)$request->user()->getKey(),404);
        $device->forceFill(['revoked_at'=>now()])->save();
        return back()->with('status','دسترسی Sync دستگاه لغو شد.');
    }

    public function restoreDevice(Request $request, Device $device): RedirectResponse
    {
        abort_unless((string)$device->user_id === (string)$request->user()->getKey(),404);
        $device->forceFill(['revoked_at'=>null])->save();
        return back()->with('status','دستگاه دوباره فعال شد.');
    }
}

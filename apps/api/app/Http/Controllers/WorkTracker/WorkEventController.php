<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\Device;
use App\Models\Project;
use App\Models\WorkEvent;
use App\Services\WorkEventMaterializer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkEventController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();
        $timezone = (string) config('worktracker.display_timezone', 'Asia/Tehran');
        $date = (string) ($request->query('date') ?: now($timezone)->toDateString());
        $deviceId = $request->query('device_id');
        $projectId = $request->query('project_id');
        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->addDay();

        $query = WorkEvent::query()
            ->where('user_id', $userId)
            ->whereDate('projection_date', $date)
            ->with([
                'project:id,name,code',
                'device:id,name,operator_label',
                'segments.activitySession:id,process_name,window_title,source,activity_type_id,activity_type_confidence,activity_type_source,activity_type_reason',
                'segments.activitySession.activityType:id,name',
                'bridges',
            ])
            ->orderBy('started_at');

        if ($deviceId) $query->where('device_id', $deviceId);
        if ($projectId) $query->where('project_id', $projectId);
        $events = $query->get();

        $rawQuery = ActivitySession::query()
            ->where('user_id', $userId)
            ->where('ended_at', '>', $dayStart->utc())
            ->where('started_at', '<', $dayEnd->utc());
        if ($deviceId) $rawQuery->where('device_id', $deviceId);
        if ($projectId) $rawQuery->where('project_id', $projectId);
        $rawSessionsCount = $rawQuery->count();

        $interruptedProjectIds = $events
            ->flatMap(fn (WorkEvent $event) => $event->bridges->flatMap(fn ($bridge) => $bridge->interrupted_project_ids ?? []))
            ->unique()
            ->values();
        $projectNames = Project::query()
            ->where('user_id', $userId)
            ->whereIn('id', $interruptedProjectIds)
            ->pluck('name', 'id');

        return view('worktracker.work-events.index', [
            'events' => $events,
            'date' => $date,
            'timezone' => $timezone,
            'deviceId' => $deviceId,
            'projectId' => $projectId,
            'projects' => Project::query()->where('user_id', $userId)->where('is_archived', false)->orderBy('name')->get(['id', 'name']),
            'devices' => Device::query()->where('user_id', $userId)->orderBy('name')->get(['id', 'name', 'operator_label']),
            'projectNames' => $projectNames,
            'rawSessionsCount' => $rawSessionsCount,
            'summary' => [
                'direct_seconds' => (int) $events->sum('direct_seconds'),
                'bridge_seconds' => (int) $events->sum('bridge_seconds'),
                'credited_seconds' => (int) $events->sum('credited_seconds'),
                'events_count' => $events->count(),
                'bridges_count' => (int) $events->sum('bridge_count'),
                'segments_count' => (int) $events->sum('segment_count'),
            ],
        ]);
    }

    public function rebuild(Request $request, WorkEventMaterializer $materializer): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'device_id' => ['nullable', 'uuid'],
        ]);

        $result = $materializer->rebuildDate(
            $request->user()->getKey(),
            $data['date'],
            $data['device_id'] ?? null,
            (string) config('worktracker.display_timezone', 'Asia/Tehran'),
        );

        return back()->with('status', "Projection بازسازی شد: {$result['events']} رویداد، {$result['bridges']} Bridge و {$result['segments']} Segment.");
    }
}

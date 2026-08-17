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

        $baseQuery = WorkEvent::query()
            ->where('user_id', $userId)
            ->whereDate('projection_date', $date);

        if ($deviceId) $baseQuery->where('device_id', $deviceId);
        if ($projectId) $baseQuery->where('project_id', $projectId);

        $summaryRow = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(direct_seconds), 0) as direct_seconds')
            ->selectRaw('COALESCE(SUM(bridge_seconds), 0) as bridge_seconds')
            ->selectRaw('COALESCE(SUM(credited_seconds), 0) as credited_seconds')
            ->selectRaw('COUNT(*) as events_count')
            ->selectRaw('COALESCE(SUM(bridge_count), 0) as bridges_count')
            ->selectRaw('COALESCE(SUM(segment_count), 0) as segments_count')
            ->first();

        $perPage = (int) $request->integer('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 200], true) ? $perPage : 50;

        $events = (clone $baseQuery)
            ->with([
                'project:id,name,code',
                'device:id,name,operator_label',
                'segments.activitySession:id,process_name,window_title,source,activity_type_id,activity_type_confidence,activity_type_source,activity_type_reason',
                'segments.activitySession.activityType:id,name',
                'bridges',
            ])
            ->orderBy('started_at')
            ->paginate($perPage)
            ->withQueryString();

        $rawQuery = ActivitySession::query()
            ->where('user_id', $userId)
            ->where('ended_at', '>', $dayStart->utc())
            ->where('started_at', '<', $dayEnd->utc());
        if ($deviceId) $rawQuery->where('device_id', $deviceId);
        if ($projectId) $rawQuery->where('project_id', $projectId);
        $rawSessionsCount = $rawQuery->count();

        $interruptedProjectIds = $events->getCollection()
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
                'direct_seconds' => (int) ($summaryRow->direct_seconds ?? 0),
                'bridge_seconds' => (int) ($summaryRow->bridge_seconds ?? 0),
                'credited_seconds' => (int) ($summaryRow->credited_seconds ?? 0),
                'events_count' => (int) ($summaryRow->events_count ?? 0),
                'bridges_count' => (int) ($summaryRow->bridges_count ?? 0),
                'segments_count' => (int) ($summaryRow->segments_count ?? 0),
            ],
            'perPage' => $perPage,
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

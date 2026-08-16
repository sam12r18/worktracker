<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkEvent;
use App\Services\WorkEventMaterializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'device_id' => ['nullable', 'uuid'],
            'project_id' => ['nullable', 'string', 'max:36'],
        ]);
        $timezone = (string) config('worktracker.display_timezone', 'Asia/Tehran');
        $date = $data['date'] ?? now($timezone)->toDateString();
        $userId = $request->user()->getKey();

        $query = WorkEvent::query()
            ->where('user_id', $userId)
            ->whereDate('projection_date', $date)
            ->with([
                'project:id,name,code',
                'device:id,name,operator_label',
                'bridges',
                'segments:id,work_event_id,activity_session_id,position,started_at,ended_at,duration_seconds',
            ])
            ->orderBy('started_at');

        if (! empty($data['device_id'])) $query->where('device_id', $data['device_id']);
        if (! empty($data['project_id'])) $query->where('project_id', $data['project_id']);
        $events = $query->get();

        return response()->json([
            'date' => $date,
            'timezone' => $timezone,
            'projection_version' => (string) config('worktracker.activity_intelligence.projection_version', 'alpha.7.3-p1'),
            'summary' => [
                'events' => $events->count(),
                'segments' => (int) $events->sum('segment_count'),
                'bridges' => (int) $events->sum('bridge_count'),
                'direct_seconds' => (int) $events->sum('direct_seconds'),
                'bridge_seconds' => (int) $events->sum('bridge_seconds'),
                'credited_seconds' => (int) $events->sum('credited_seconds'),
            ],
            'data' => $events,
        ]);
    }

    public function rebuild(Request $request, WorkEventMaterializer $materializer): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'device_id' => ['nullable', 'uuid'],
        ]);
        $timezone = (string) config('worktracker.display_timezone', 'Asia/Tehran');

        return response()->json($materializer->rebuildDate(
            $request->user()->getKey(),
            $data['date'],
            $data['device_id'] ?? null,
            $timezone,
        ));
    }
}

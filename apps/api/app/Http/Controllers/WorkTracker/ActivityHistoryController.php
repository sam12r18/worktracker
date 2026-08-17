<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\BillingRateSnapshot;
use App\Models\Project;
use App\Models\WorkTrackerAuditLog;
use App\Services\WorkEventMaterializer;
use App\Services\WorkTrackerAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();
        $timezone = (string) ($request->query('timezone') ?: config('worktracker.display_timezone', 'Asia/Tehran'));
        $date = (string) ($request->query('date') ?: now($timezone)->toDateString());
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $end = $start->addDay();

        $query = ActivitySession::query()
            ->where('user_id', $userId)
            ->where('ended_at', '>', $start->utc())
            ->where('started_at', '<', $end->utc())
            ->with(['project:id,name', 'activityType:id,name', 'device:id,name,operator_label'])
            ->orderBy('started_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        $perPage = (int) $request->integer('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 200], true) ? $perPage : 50;

        $activities = $query
            ->paginate($perPage)
            ->withQueryString();

        $billed = BillingRateSnapshot::query()
            ->whereIn('activity_session_id', $activities->getCollection()->pluck('id'))
            ->pluck('activity_session_id')
            ->flip();

        return view('worktracker.activities.index', [
            'activities' => $activities,
            'billed' => $billed,
            'projects' => Project::query()->where('user_id', $userId)->where('is_archived', false)->orderBy('name')->get(),
            'activityTypes' => ActivityType::query()
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'date' => $date,
            'timezone' => $timezone,
            'perPage' => $perPage,
        ]);
    }

    public function update(
        Request $request,
        ActivitySession $activity,
        WorkTrackerAuditService $audit,
        WorkEventMaterializer $workEvents,
    ): RedirectResponse {
        $userId = $request->user()->getKey();
        abort_unless((string) $activity->user_id === (string) $userId, 404);
        abort_if(
            BillingRateSnapshot::query()->where('activity_session_id', $activity->id)->exists(),
            409,
            'فعالیت داخل فاکتور نهایی است و قابل ویرایش مستقیم نیست.'
        );

        $data = $request->validate([
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'activity_type_id' => ['nullable', 'uuid', Rule::exists('activity_types', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'is_billable' => ['nullable', 'in:default,yes,no'],
            'note' => ['nullable', 'string', 'max:20000'],
            'reason' => ['required', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
        ]);

        $before = $activity->only([
            'project_id', 'activity_type_id', 'activity_type_confidence', 'activity_type_source', 'activity_type_reason', 'started_at', 'ended_at',
            'duration_seconds', 'is_billable', 'note', 'version',
        ]);
        $beforeStartedAt = CarbonImmutable::parse($activity->started_at);
        $beforeEndedAt = CarbonImmutable::parse($activity->ended_at);

        DB::transaction(function () use ($activity, $data): void {
            $start = CarbonImmutable::parse($data['started_at'], $data['timezone'])->utc();
            $end = CarbonImmutable::parse($data['ended_at'], $data['timezone'])->utc();
            $activity->project_id = $data['project_id'] ?: null;
            $activity->activity_type_id = $data['activity_type_id'] ?: null;
            $activity->activity_type_confidence = $activity->activity_type_id ? 1.0 : null;
            $activity->activity_type_source = $activity->activity_type_id ? 'user_override' : null;
            $activity->activity_type_reason = $activity->activity_type_id ? 'web_historical_correction' : null;
            $activity->started_at = $start;
            $activity->ended_at = $end;
            $activity->duration_seconds = max(1, $start->diffInSeconds($end));
            $activity->is_billable = match ($data['is_billable'] ?? 'default') {
                'yes' => true,
                'no' => false,
                default => null,
            };
            $activity->note = $data['note'] ?: null;
            $activity->version = (int) $activity->version + 1;
            $activity->save();
        });

        $audit->record(
            $request,
            'activity_session',
            (string) $activity->id,
            'historical_update',
            $before,
            $activity->fresh()->only([
                'project_id', 'activity_type_id', 'activity_type_confidence', 'activity_type_source', 'activity_type_reason', 'started_at', 'ended_at',
                'duration_seconds', 'is_billable', 'note', 'version',
            ]),
            $data['reason']
        );

        // Rebuild both the old and new local dates. Editing timestamps can move an Activity across a day boundary.
        $displayTimezone = (string) config('worktracker.display_timezone', 'Asia/Tehran');
        $newActivity = $activity->fresh();
        $dates = collect($this->projectionDates($beforeStartedAt, $beforeEndedAt, $displayTimezone))
            ->merge($this->projectionDates(
                CarbonImmutable::parse($newActivity->started_at),
                CarbonImmutable::parse($newActivity->ended_at),
                $displayTimezone
            ))
            ->unique()
            ->values();

        foreach ($dates as $projectionDate) {
            $workEvents->rebuildDate($userId, $projectionDate, (string) $activity->device_id, $displayTimezone);
        }

        return back()->with('status', 'فعالیت ویرایش شد، Audit Log ثبت و Work Eventها بازسازی شدند.');
    }

    public function audit(Request $request): View
    {
        $logs = WorkTrackerAuditLog::query()
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->paginate(100);

        return view('worktracker.activities.audit', ['logs' => $logs]);
    }

    /** @return list<string> */
    private function projectionDates(CarbonImmutable $startedAt, CarbonImmutable $endedAt, string $timezone): array
    {
        $start = $startedAt->setTimezone($timezone)->startOfDay();
        $last = $endedAt->setTimezone($timezone)->subSecond()->startOfDay();
        $dates = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($last); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
            if (count($dates) >= 8) break;
        }

        return $dates;
    }
}

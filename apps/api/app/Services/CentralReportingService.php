<?php

namespace App\Services;

use App\Models\ActivitySession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CentralReportingService
{
    public function __construct(
        private readonly TimeAccountingService $accounting,
        private readonly WorkEventProjectionService $workEvents,
    ) {}

    public function daily(int|string $userId, CarbonImmutable $dayStart, ?string $deviceId = null): array
    {
        return $this->range($userId, $dayStart, $dayStart->addDay(), $deviceId);
    }

    public function range(int|string $userId, CarbonImmutable $from, CarbonImmutable $to, ?string $deviceId = null): array
    {
        $query = $this->baseQuery($userId, $from, $to);
        if ($deviceId) $query->where('device_id', $deviceId);

        $sessions = $query->with(['project:id,name,code', 'device:id,name,operator_label', 'activityType:id,name,code'])->get();
        $projection = $this->workEvents->projectRange($sessions, $from, $to);
        $summary = $this->summarizeWithProjection($sessions, $projection, $from, $to);

        $days = collect();
        for ($cursor = $from->startOfDay(); $cursor->lessThan($to); $cursor = $cursor->addDay()) {
            $start = $cursor->lessThan($from) ? $from : $cursor;
            $end = $cursor->addDay()->greaterThan($to) ? $to : $cursor->addDay();
            $rows = $sessions->filter(fn($s) => CarbonImmutable::parse($s->ended_at)->greaterThan($start) && CarbonImmutable::parse($s->started_at)->lessThan($end))->values();
            $dayProjection = $this->workEvents->projectRange($rows, $start, $end);
            $days->push([
                'date'=>$cursor->toDateString(),
                'sessions_count'=>$rows->count(),
            ] + $this->summarizeWithProjection($rows, $dayProjection, $start, $end));
        }

        return [
            'range'=>['from'=>$from->toISOString(), 'to'=>$to->toISOString()],
            'summary'=>$summary,
            'projects'=>$this->groupByProject($sessions, $projection, $from, $to),
            'devices'=>$this->groupByDevice($sessions, $projection, $from, $to),
            'sources'=>$this->groupBySource($sessions, $from, $to),
            'activity_types'=>$this->groupByActivityType($sessions, $from, $to),
            'unknown'=>$this->groupUnknown($sessions, $from, $to),
            'days'=>$days,
            'sessions_count'=>$sessions->count(),
            'work_events_count'=>count($projection['events']),
            'bridges_count'=>$summary['bridges_count'],
        ];
    }

    public function project(int|string $userId, string $projectId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sessions = $this->baseQuery($userId, $from, $to)
            ->where('project_id', $projectId)
            ->with(['project:id,name,code', 'device:id,name,operator_label', 'activityType:id,name,code'])
            ->get();

        // Project continuity can be interrupted by other Projects, so projection must see the full foreground stream.
        $allSessions = $this->baseQuery($userId, $from, $to)
            ->with(['project:id,name,code', 'device:id,name,operator_label', 'activityType:id,name,code'])
            ->get();
        $allProjection = $this->workEvents->projectRange($allSessions, $from, $to);
        $projectEvents = array_values(array_filter($allProjection['events'], static fn(array $event): bool => (string) ($event['project_id'] ?? '') === $projectId));
        $projectProjection = ['events'=>$projectEvents, 'decisions'=>array_values(array_filter($allProjection['decisions'], static fn(array $d): bool => (string) $d['project_id'] === $projectId))];
        $summary = $this->summarizeWithProjection($sessions, $projectProjection, $from, $to);

        return [
            'project_id'=>$projectId,
            'range'=>['from'=>$from->toISOString(), 'to'=>$to->toISOString()],
            'summary'=>$summary,
            'devices'=>$this->groupByDevice($sessions, $projectProjection, $from, $to),
            'sources'=>$this->groupBySource($sessions, $from, $to),
            'activity_types'=>$this->groupByActivityType($sessions, $from, $to),
            'days'=>$this->daysForProject($sessions, $allSessions, $projectId, $from, $to),
            'sessions_count'=>$sessions->count(),
            'work_events_count'=>count($projectEvents),
            'bridges_count'=>$summary['bridges_count'],
        ];
    }

    public function sessions(int|string $userId, CarbonImmutable $from, CarbonImmutable $to, ?string $projectId = null): Collection
    {
        $query = $this->baseQuery($userId, $from, $to);
        if ($projectId) $query->where('project_id', $projectId);
        return $query->with(['project:id,name,code', 'device:id,name,operator_label', 'activityType:id,name,code'])->get();
    }

    /** @return list<array<string,mixed>> */
    public function projectedEvents(int|string $userId, CarbonImmutable $from, CarbonImmutable $to, ?string $projectId = null): array
    {
        $sessions = $this->sessions($userId, $from, $to, null);
        $projection = $this->workEvents->projectRange($sessions, $from, $to);
        if (! $projectId) return $projection['events'];
        return array_values(array_filter($projection['events'], static fn(array $event): bool => (string) ($event['project_id'] ?? '') === $projectId));
    }

    private function summarizeWithProjection(Collection $sessions, array $projection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->workEvents->summarizeProjection($projection, $this->accounting->summarize($sessions, $from, $to));
    }

    private function daysForProject(Collection $projectSessions, Collection $allSessions, string $projectId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $out = collect();
        for ($cursor = $from->startOfDay(); $cursor->lessThan($to); $cursor = $cursor->addDay()) {
            $start = $cursor->lessThan($from) ? $from : $cursor;
            $end = $cursor->addDay()->greaterThan($to) ? $to : $cursor->addDay();
            $rows = $projectSessions->filter(fn($s) => CarbonImmutable::parse($s->ended_at)->greaterThan($start) && CarbonImmutable::parse($s->started_at)->lessThan($end))->values();
            $dayAll = $allSessions->filter(fn($s) => CarbonImmutable::parse($s->ended_at)->greaterThan($start) && CarbonImmutable::parse($s->started_at)->lessThan($end))->values();
            $projection = $this->workEvents->projectRange($dayAll, $start, $end);
            $events = array_values(array_filter($projection['events'], static fn(array $event): bool => (string) ($event['project_id'] ?? '') === $projectId));
            $out->push([
                'date'=>$cursor->toDateString(),
                'sessions_count'=>$rows->count(),
            ] + $this->summarizeWithProjection($rows, ['events'=>$events, 'decisions'=>[]], $start, $end));
        }
        return $out;
    }

    private function baseQuery(int|string $userId, CarbonImmutable $from, CarbonImmutable $to)
    {
        // Database timestamps remain UTC even when the report range is expressed in Asia/Tehran.
        // Laravel query bindings preserve the Carbon wall-clock value, so normalize boundaries here.
        return ActivitySession::query()
            ->where('user_id', $userId)
            ->where('ended_at', '>', $from->utc())
            ->where('started_at', '<', $to->utc())
            ->orderBy('started_at');
    }

    private function groupByProject(Collection $sessions, array $projection, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $bridgeByProject = collect($projection['events'])
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->map(fn(Collection $events): array => [
                'bridge_seconds'=>$events->sum('bridge_seconds'),
                'work_events_count'=>$events->count(),
                'bridges_count'=>$events->sum(fn(array $event): int => count($event['bridges'])),
            ]);

        return $sessions->groupBy(fn($x) => $x->project_id ?: '__unknown__')->map(function(Collection $rows, string $key) use ($from, $to, $bridgeByProject) {
            $first = $rows->first();
            $raw = $this->accounting->summarize($rows, $from, $to);
            $bridgeInfo = $key === '__unknown__' ? null : $bridgeByProject->get($key);
            $bridge = (int) ($bridgeInfo['bridge_seconds'] ?? 0);
            $credited = $raw['effort_seconds'] + $bridge;
            return [
                'project_id'=>$key === '__unknown__' ? null : $key,
                'name'=>$first->project?->name ?? 'تشخیص‌داده‌نشده',
                'code'=>$first->project?->code,
                'sessions_count'=>$rows->count(),
                'raw_effort_seconds'=>$raw['effort_seconds'],
                'continuity_bridge_seconds'=>$bridge,
                'effort_seconds'=>$credited,
                'elapsed_coverage_seconds'=>$raw['elapsed_coverage_seconds'],
                'concurrent_effort_seconds'=>max(0, $credited - $raw['elapsed_coverage_seconds']),
                'work_events_count'=>(int) ($bridgeInfo['work_events_count'] ?? 0),
                'bridges_count'=>(int) ($bridgeInfo['bridges_count'] ?? 0),
            ];
        })->sortByDesc('effort_seconds')->values();
    }

    private function groupByDevice(Collection $sessions, array $projection, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $bridgeByDevice = collect($projection['events'])->groupBy('device_id')->map(fn(Collection $events): array => [
            'bridge_seconds'=>$events->sum('bridge_seconds'),
            'work_events_count'=>$events->count(),
            'bridges_count'=>$events->sum(fn(array $event): int => count($event['bridges'])),
        ]);

        return $sessions->groupBy('device_id')->map(function(Collection $rows, string $id) use ($from, $to, $bridgeByDevice) {
            $device = $rows->first()->device;
            $raw = $this->accounting->summarize($rows, $from, $to);
            $bridgeInfo = $bridgeByDevice->get($id, []);
            $bridge = (int) ($bridgeInfo['bridge_seconds'] ?? 0);
            $credited = $raw['effort_seconds'] + $bridge;
            return [
                'device_id'=>$id,
                'name'=>$device?->name ?? $id,
                'operator_label'=>$device?->operator_label,
                'sessions_count'=>$rows->count(),
                'raw_effort_seconds'=>$raw['effort_seconds'],
                'continuity_bridge_seconds'=>$bridge,
                'effort_seconds'=>$credited,
                'elapsed_coverage_seconds'=>$raw['elapsed_coverage_seconds'],
                'concurrent_effort_seconds'=>max(0, $credited - $raw['elapsed_coverage_seconds']),
                'work_events_count'=>(int) ($bridgeInfo['work_events_count'] ?? 0),
                'bridges_count'=>(int) ($bridgeInfo['bridges_count'] ?? 0),
            ];
        })->sortByDesc('effort_seconds')->values();
    }

    private function groupBySource(Collection $sessions, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $sessions->groupBy('source')->map(fn(Collection $rows, string $source) => [
            'source'=>$source,
            'sessions_count'=>$rows->count(),
        ] + $this->accounting->summarize($rows, $from, $to))->sortByDesc('effort_seconds')->values();
    }

    private function groupByActivityType(Collection $sessions, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $sessions->groupBy(fn($x) => $x->activity_type_id ?: '__none__')->map(function(Collection $rows, string $id) use ($from, $to) {
            $first = $rows->first();
            return [
                'activity_type_id'=>$id === '__none__' ? null : $id,
                'name'=>$first->activityType?->name ?? 'بدون نوع',
                'sessions_count'=>$rows->count(),
            ] + $this->accounting->summarize($rows, $from, $to);
        })->sortByDesc('effort_seconds')->values();
    }

    private function groupUnknown(Collection $sessions, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $unknown = $sessions->whereNull('project_id')->values();
        return ['sessions_count'=>$unknown->count()] + $this->accounting->summarize($unknown, $from, $to);
    }
}

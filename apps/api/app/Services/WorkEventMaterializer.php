<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Models\WorkEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WorkEventMaterializer
{
    public function __construct(private readonly WorkEventProjectionService $projection) {}

    /**
     * Rebuild derived Work Events for one local calendar date. Raw sessions are never modified.
     * When deviceId is null, all devices that have raw or previously materialized events on the date are rebuilt.
     *
     * @return array{date:string,timezone:string,devices:int,events:int,bridges:int,segments:int,decision_reasons:array<string,int>}
     */
    public function rebuildDate(int|string $userId, string $date, ?string $deviceId = null, ?string $timezone = null, ?string $correlationId = null): array
    {
        $timezone ??= (string) config('worktracker.display_timezone', 'Asia/Tehran');
        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->addDay();

        // Activity timestamps are stored in UTC. Query bindings do not convert Carbon timezones,
        // so convert the local Tehran day boundaries explicitly before hitting MySQL.
        $queryStartUtc = $dayStart->utc();
        $queryEndUtc = $dayEnd->utc();

        $deviceIds = $deviceId ? collect([$deviceId]) : ActivitySession::query()
            ->where('user_id', $userId)
            ->where('ended_at', '>', $queryStartUtc)
            ->where('started_at', '<', $queryEndUtc)
            ->distinct()
            ->pluck('device_id')
            ->merge(WorkEvent::query()->where('user_id', $userId)->whereDate('projection_date', $dayStart->toDateString())->pluck('device_id'))
            ->unique()
            ->values();

        $totals = [
            'date'=>$dayStart->toDateString(),
            'timezone'=>$timezone,
            'devices'=>0,
            'events'=>0,
            'bridges'=>0,
            'segments'=>0,
            'decision_reasons'=>[],
        ];

        foreach ($deviceIds as $id) {
            $result = $this->rebuildDeviceDate($userId, (string) $id, $dayStart, $dayEnd, $timezone);
            $totals['devices']++;
            $totals['events'] += $result['events'];
            $totals['bridges'] += $result['bridges'];
            $totals['segments'] += $result['segments'];
            foreach ($result['decision_reasons'] as $reason => $count) {
                $totals['decision_reasons'][$reason] = ($totals['decision_reasons'][$reason] ?? 0) + $count;
            }
        }

        Log::channel('worktracker_sync')->info('projection.rebuilt', [
            'correlation_id'=>$correlationId,
            'user_id'=>$userId,
            'projection_date'=>$totals['date'],
            'timezone'=>$timezone,
            'device_id'=>$deviceId,
            'devices'=>$totals['devices'],
            'events'=>$totals['events'],
            'bridges'=>$totals['bridges'],
            'segments'=>$totals['segments'],
            'decision_reasons'=>$totals['decision_reasons'],
            'projection_version'=>WorkEventProjectionService::PROJECTION_VERSION,
        ]);

        return $totals;
    }

    /** @return array{events:int,bridges:int,segments:int,decision_reasons:array<string,int>} */
    private function rebuildDeviceDate(int|string $userId, string $deviceId, CarbonImmutable $dayStart, CarbonImmutable $dayEnd, string $timezone): array
    {
        $sessions = ActivitySession::query()
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('ended_at', '>', $dayStart->utc())
            ->where('started_at', '<', $dayEnd->utc())
            ->orderBy('started_at')
            ->orderBy('ended_at')
            ->get();

        $projection = $this->projection->projectRange($sessions, $dayStart, $dayEnd);
        $calculatedAt = now();
        $date = $dayStart->toDateString();
        $events = $projection['events'];
        $eventRows = [];
        $segmentRows = [];
        $bridgeRows = [];

        foreach ($events as $event) {
            $eventRows[] = [
                'id'=>$event['id'],
                'user_id'=>$userId,
                'device_id'=>$deviceId,
                'project_id'=>$event['project_id'],
                'projection_date'=>$date,
                'timezone'=>$timezone,
                'event_kind'=>$event['event_kind'],
                'context_key'=>mb_substr((string) $event['context_key'], 0, 255),
                'started_at'=>$event['started_at'],
                'ended_at'=>$event['ended_at'],
                'direct_seconds'=>$event['direct_seconds'],
                'bridge_seconds'=>$event['bridge_seconds'],
                'credited_seconds'=>$event['credited_seconds'],
                'segment_count'=>count($event['sessions']),
                'bridge_count'=>count($event['bridges']),
                'applications'=>json_encode($event['applications'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'projection_version'=>WorkEventProjectionService::PROJECTION_VERSION,
                'calculated_at'=>$calculatedAt,
                'created_at'=>$calculatedAt,
                'updated_at'=>$calculatedAt,
            ];

            foreach ($event['sessions'] as $position => $session) {
                $segmentRows[] = [
                    'work_event_id'=>$event['id'],
                    'activity_session_id'=>$session['id'],
                    'position'=>$position,
                    'started_at'=>$session['started_at'],
                    'ended_at'=>$session['ended_at'],
                    'duration_seconds'=>$session['duration_seconds'],
                    'created_at'=>$calculatedAt,
                    'updated_at'=>$calculatedAt,
                ];
            }

            foreach ($event['bridges'] as $bridge) {
                $bridgeId = hash('sha256', implode('|', [
                    WorkEventProjectionService::PROJECTION_VERSION,
                    $event['id'],
                    $bridge['anchor_project_id'],
                    $bridge['started_at']->toISOString(),
                    $bridge['ended_at']->toISOString(),
                ]));
                $bridgeRows[] = [
                    'id'=>$bridgeId,
                    'work_event_id'=>$event['id'],
                    'user_id'=>$userId,
                    'device_id'=>$deviceId,
                    'anchor_project_id'=>$bridge['anchor_project_id'],
                    'projection_date'=>$date,
                    'started_at'=>$bridge['started_at'],
                    'ended_at'=>$bridge['ended_at'],
                    'duration_seconds'=>$bridge['duration_seconds'],
                    'interrupted_project_ids'=>json_encode($bridge['interrupted_project_ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'reason'=>$bridge['reason'],
                    'projection_version'=>WorkEventProjectionService::PROJECTION_VERSION,
                    'created_at'=>$calculatedAt,
                    'updated_at'=>$calculatedAt,
                ];
            }
        }

        DB::transaction(function () use ($userId, $deviceId, $date, $eventRows, $segmentRows, $bridgeRows) {
            WorkEvent::query()->where('user_id', $userId)->where('device_id', $deviceId)->whereDate('projection_date', $date)->delete();
            if ($eventRows !== []) DB::table('work_events')->insert($eventRows);
            if ($segmentRows !== []) DB::table('work_event_segments')->insert($segmentRows);
            if ($bridgeRows !== []) DB::table('continuity_bridges')->insert($bridgeRows);
        });

        $decisionReasons = collect($projection['decisions'])
            ->countBy(fn (array $decision): string => (string) $decision['reason'])
            ->all();

        return [
            'events'=>count($eventRows),
            'bridges'=>count($bridgeRows),
            'segments'=>count($segmentRows),
            'decision_reasons'=>$decisionReasons,
        ];
    }
}

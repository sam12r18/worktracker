<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class WorkEventProjectionService
{
    public const PROJECTION_VERSION = 'alpha.8.0-p1';
    public const MERGE_GAP_SECONDS = 15;
    public const INITIAL_ANCHOR_SECONDS = 60;
    public const BRIDGE_MAX_SECONDS = 120;
    public const BRIDGE_REARM_SECONDS = 120;

    public function __construct(private readonly WorkEventContextNormalizer $contexts) {}

    /**
     * Project a time range without mutating raw Activity Sessions.
     * Continuity state is independent per device and per Project; mutual/multi-project bridges are valid.
     *
     * @return array{events:list<array<string,mixed>>,decisions:list<array<string,mixed>>}
     */
    public function projectRange(Collection $sessions, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $clipped = $sessions->map(function ($session) use ($rangeStart, $rangeEnd) {
            $started = CarbonImmutable::parse($session->started_at);
            $ended = CarbonImmutable::parse($session->ended_at);
            $start = $started->greaterThan($rangeStart) ? $started : $rangeStart;
            $end = $ended->lessThan($rangeEnd) ? $ended : $rangeEnd;
            if ($end->lessThanOrEqualTo($start)) return null;

            return [
                'id' => (string) $session->getKey(),
                'device_id' => (string) $session->device_id,
                'project_id' => $session->project_id ? (string) $session->project_id : null,
                'activity_type_id' => $session->activity_type_id ? (string) $session->activity_type_id : null,
                'source' => (string) $session->source,
                'process_name' => $session->process_name ? (string) $session->process_name : null,
                'window_title' => $session->window_title ? (string) $session->window_title : null,
                'ide_context' => is_array($session->ide_context) ? $session->ide_context : null,
                'started_at' => $start,
                'ended_at' => $end,
                'duration_seconds' => max(0, (int) floor($start->diffInSeconds($end))),
            ];
        })->filter()->values();

        return $this->projectRows($clipped);
    }

    /** @param Collection<int,array<string,mixed>> $sessions */
    public function projectRows(Collection $sessions): array
    {
        $events = [];
        $decisions = [];

        foreach ($sessions->groupBy('device_id') as $deviceId => $deviceSessions) {
            $ordered = $deviceSessions->sortBy([
                ['started_at', 'asc'],
                ['ended_at', 'asc'],
            ])->values();
            $automatic = $ordered->where('source', 'auto_foreground')->values();
            $manual = $ordered->where('source', '!=', 'auto_foreground')->values();

            $baseSpans = $this->buildForegroundSpans($automatic);
            [$projected, $projectDecisions] = $this->buildProjectWorkEvents((string) $deviceId, $baseSpans);
            array_push($events, ...$projected);
            array_push($decisions, ...$projectDecisions);

            foreach ($baseSpans as $span) {
                if ($span['project_id'] !== null) continue;
                $events[] = $this->spanToEvent((string) $deviceId, $span, 'unknown_foreground');
            }

            foreach ($manual as $session) {
                $events[] = $this->manualEvent((string) $deviceId, $session);
            }
        }

        usort($events, static fn(array $a, array $b): int => [$b['started_at']->getTimestamp(), $b['ended_at']->getTimestamp()] <=> [$a['started_at']->getTimestamp(), $a['ended_at']->getTimestamp()]);

        return ['events'=>$events, 'decisions'=>$decisions];
    }

    /** @return array{raw_effort_seconds:int,continuity_bridge_seconds:int,effort_seconds:int,elapsed_coverage_seconds:int,concurrent_effort_seconds:int,work_events_count:int,bridges_count:int} */
    public function summarizeProjection(array $projection, array $rawSummary): array
    {
        $bridgeSeconds = array_sum(array_map(static fn(array $event): int => (int) $event['bridge_seconds'], $projection['events']));
        $bridgeCount = array_sum(array_map(static fn(array $event): int => count($event['bridges']), $projection['events']));
        $rawEffort = (int) ($rawSummary['effort_seconds'] ?? 0);
        $coverage = (int) ($rawSummary['elapsed_coverage_seconds'] ?? 0);
        $credited = $rawEffort + $bridgeSeconds;

        return [
            'raw_effort_seconds' => $rawEffort,
            'continuity_bridge_seconds' => $bridgeSeconds,
            'effort_seconds' => $credited,
            'elapsed_coverage_seconds' => $coverage,
            'concurrent_effort_seconds' => max(0, $credited - $coverage),
            'work_events_count' => count($projection['events']),
            'bridges_count' => $bridgeCount,
        ];
    }

    /** @param Collection<int,array<string,mixed>> $sessions @return list<array<string,mixed>> */
    private function buildForegroundSpans(Collection $sessions): array
    {
        $result = [];
        foreach ($sessions as $session) {
            $groupKey = $this->foregroundGroupKey($session);
            $lastIndex = count($result) - 1;
            if ($lastIndex >= 0 &&
                $result[$lastIndex]['group_key'] === $groupKey &&
                $session['started_at']->lessThanOrEqualTo($result[$lastIndex]['ended_at']->addSeconds(self::MERGE_GAP_SECONDS))) {
                $result[$lastIndex] = $this->addSessionToSpan($result[$lastIndex], $session);
                continue;
            }
            $result[] = $this->newSpan($groupKey, $session);
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $baseSpans @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>} */
    private function buildProjectWorkEvents(string $deviceId, array $baseSpans): array
    {
        $result = [];
        $decisions = [];
        $projectIds = array_values(array_unique(array_filter(array_map(static fn(array $x) => $x['project_id'], $baseSpans))));

        foreach ($projectIds as $projectId) {
            $directSpans = array_values(array_filter($baseSpans, static fn(array $x): bool => $x['project_id'] === $projectId));
            usort($directSpans, static fn(array $a, array $b): int => [$a['started_at']->getTimestamp(), $a['ended_at']->getTimestamp()] <=> [$b['started_at']->getTimestamp(), $b['ended_at']->getTimestamp()]);
            if ($directSpans === []) continue;

            $chain = $this->cloneSpan($directSpans[0]);
            $directSinceLastBridge = $this->spanDirectSeconds($chain);
            $hasBridge = false;
            $decisions[] = $this->decision($projectId, 'Direct', $chain['started_at'], 'anchor_started', $directSinceLastBridge);

            for ($i = 1, $n = count($directSpans); $i < $n; $i++) {
                $next = $directSpans[$i];
                if ($next['started_at']->lessThanOrEqualTo($chain['ended_at']->addSeconds(self::MERGE_GAP_SECONDS))) {
                    $chain = $this->mergeDirect($chain, $next);
                    $directSinceLastBridge += $this->spanDirectSeconds($next);
                    $decisions[] = $this->decision($projectId, 'Direct', $next['started_at'], 'same_project_continuation', $directSinceLastBridge);
                    continue;
                }

                $gapSeconds = max(0, (int) floor($chain['ended_at']->diffInSeconds($next['started_at'])));
                $interruptedProjects = $this->findInterruptedProjects($baseSpans, $chain['ended_at'], $next['started_at'], $projectId);
                $decisions[] = $this->decision($projectId, 'Suspended', $chain['ended_at'], 'foreground_left_project', $directSinceLastBridge, $gapSeconds, $interruptedProjects);

                $requiredDirect = $hasBridge ? self::BRIDGE_REARM_SECONDS : self::INITIAL_ANCHOR_SECONDS;
                $eligibleByAnchor = $directSinceLastBridge >= $requiredDirect;
                $eligibleByGap = $gapSeconds > 0 && $gapSeconds <= self::BRIDGE_MAX_SECONDS;
                $fullyObserved = $eligibleByGap && $this->hasContinuousObservedInterruption($baseSpans, $chain['ended_at'], $next['started_at'], $projectId);

                if ($eligibleByAnchor && $eligibleByGap && $fullyObserved) {
                    $decisions[] = $this->decision($projectId, 'BridgeCandidate', $chain['ended_at'], 'bounded_observed_interruption', $directSinceLastBridge, $gapSeconds, $interruptedProjects);
                    $chain['bridges'][] = [
                        'started_at' => $chain['ended_at'],
                        'ended_at' => $next['started_at'],
                        'duration_seconds' => $gapSeconds,
                        'anchor_project_id' => $projectId,
                        'interrupted_project_ids' => $interruptedProjects,
                        'reason' => 'continuity_restored',
                    ];
                    $chain = $this->mergeDirect($chain, $next);
                    $hasBridge = true;
                    $directSinceLastBridge = $this->spanDirectSeconds($next);
                    $decisions[] = $this->decision($projectId, 'Bridged', $next['started_at'], 'continuity_restored', $directSinceLastBridge, $gapSeconds, $interruptedProjects);
                    continue;
                }

                $reason = ! $eligibleByAnchor
                    ? ($hasBridge ? 'bridge_rearm_not_ready' : 'initial_anchor_not_ready')
                    : (! $eligibleByGap ? 'interruption_exceeds_bridge_limit' : 'interruption_not_continuously_observed');
                $decisions[] = $this->decision($projectId, 'Closed', $chain['ended_at'], $reason, $directSinceLastBridge, $gapSeconds, $interruptedProjects);
                $result[] = $this->spanToEvent($deviceId, $chain, 'foreground');

                $chain = $this->cloneSpan($next);
                $directSinceLastBridge = $this->spanDirectSeconds($chain);
                $hasBridge = false;
                $decisions[] = $this->decision($projectId, 'Direct', $chain['started_at'], 'anchor_started', $directSinceLastBridge);
            }

            $decisions[] = $this->decision($projectId, 'Closed', $chain['ended_at'], 'end_of_observed_data', $directSinceLastBridge);
            $result[] = $this->spanToEvent($deviceId, $chain, 'foreground');
        }

        return [$result, $decisions];
    }

    /** @param list<array<string,mixed>> $spans */
    private function hasContinuousObservedInterruption(array $spans, CarbonImmutable $gapStart, CarbonImmutable $gapEnd, string $anchorProjectId): bool
    {
        $interruptions = array_values(array_filter($spans, static fn(array $x): bool =>
            $x['ended_at']->greaterThan($gapStart) &&
            $x['started_at']->lessThan($gapEnd) &&
            $x['project_id'] !== $anchorProjectId
        ));
        usort($interruptions, static fn(array $a, array $b): int => $a['started_at']->getTimestamp() <=> $b['started_at']->getTimestamp());
        if ($interruptions === []) return false;

        $cursor = $gapStart;
        foreach ($interruptions as $interruption) {
            $start = $interruption['started_at']->lessThan($gapStart) ? $gapStart : $interruption['started_at'];
            $end = $interruption['ended_at']->greaterThan($gapEnd) ? $gapEnd : $interruption['ended_at'];
            if ($start->greaterThan($cursor->addSeconds(self::MERGE_GAP_SECONDS))) return false;
            if ($end->greaterThan($cursor)) $cursor = $end;
            if ($cursor->greaterThanOrEqualTo($gapEnd->subSeconds(self::MERGE_GAP_SECONDS))) return true;
        }

        return $cursor->greaterThanOrEqualTo($gapEnd->subSeconds(self::MERGE_GAP_SECONDS));
    }

    /** @param list<array<string,mixed>> $spans @return list<string> */
    private function findInterruptedProjects(array $spans, CarbonImmutable $gapStart, CarbonImmutable $gapEnd, string $anchorProjectId): array
    {
        $ids = [];
        foreach ($spans as $span) {
            if (! $span['ended_at']->greaterThan($gapStart) || ! $span['started_at']->lessThan($gapEnd)) continue;
            $id = $span['project_id'];
            if ($id === null || $id === $anchorProjectId) continue;
            $ids[$id] = true;
        }
        return array_keys($ids);
    }

    /** @param array<string,mixed> $session */
    private function foregroundGroupKey(array $session): string
    {
        if ($session['project_id'] !== null) return 'project:'.$session['project_id'];
        $context = $this->contexts->describe($session['process_name'], $session['window_title'], $session['ide_context'] ?? null);
        return 'unknown:'.$context['key'];
    }

    /** @param array<string,mixed> $session @return array<string,mixed> */
    private function newSpan(string $groupKey, array $session): array
    {
        return [
            'group_key' => $groupKey,
            'project_id' => $session['project_id'],
            'started_at' => $session['started_at'],
            'ended_at' => $session['ended_at'],
            'sessions' => [$session],
            'bridges' => [],
        ];
    }

    /** @param array<string,mixed> $span @param array<string,mixed> $session */
    private function addSessionToSpan(array $span, array $session): array
    {
        $span['sessions'][] = $session;
        if ($session['started_at']->lessThan($span['started_at'])) $span['started_at'] = $session['started_at'];
        if ($session['ended_at']->greaterThan($span['ended_at'])) $span['ended_at'] = $session['ended_at'];
        return $span;
    }

    /** @param array<string,mixed> $chain @param array<string,mixed> $other */
    private function mergeDirect(array $chain, array $other): array
    {
        array_push($chain['sessions'], ...$other['sessions']);
        if ($other['started_at']->lessThan($chain['started_at'])) $chain['started_at'] = $other['started_at'];
        if ($other['ended_at']->greaterThan($chain['ended_at'])) $chain['ended_at'] = $other['ended_at'];
        return $chain;
    }

    /** @param array<string,mixed> $span */
    private function cloneSpan(array $span): array
    {
        $span['sessions'] = array_values($span['sessions']);
        $span['bridges'] = array_values($span['bridges']);
        return $span;
    }

    /** @param array<string,mixed> $span */
    private function spanDirectSeconds(array $span): int
    {
        return array_sum(array_map(static fn(array $session): int => (int) $session['duration_seconds'], $span['sessions']));
    }

    /** @param array<string,mixed> $span @return array<string,mixed> */
    private function spanToEvent(string $deviceId, array $span, string $kind): array
    {
        usort($span['sessions'], static fn(array $a, array $b): int => [$a['started_at']->getTimestamp(), $a['ended_at']->getTimestamp()] <=> [$b['started_at']->getTimestamp(), $b['ended_at']->getTimestamp()]);
        $direct = $this->spanDirectSeconds($span);
        $bridge = array_sum(array_map(static fn(array $x): int => (int) $x['duration_seconds'], $span['bridges']));
        $applications = array_values(array_unique(array_filter(array_map(static fn(array $x) => $x['process_name'], $span['sessions']))));
        $fingerprint = implode('|', [
            self::PROJECTION_VERSION, $deviceId, (string) ($span['project_id'] ?? 'unknown'), $kind,
            $span['started_at']->toISOString(), $span['ended_at']->toISOString(),
            implode(',', array_map(static fn(array $x): string => (string) $x['id'], $span['sessions'])),
            implode(',', array_map(static fn(array $x): string => $x['started_at']->toISOString().'/'.$x['ended_at']->toISOString(), $span['bridges'])),
        ]);

        return [
            'id' => hash('sha256', $fingerprint),
            'device_id' => $deviceId,
            'project_id' => $span['project_id'],
            'event_kind' => $kind,
            'context_key' => $span['group_key'],
            'started_at' => $span['started_at'],
            'ended_at' => $span['ended_at'],
            'direct_seconds' => $direct,
            'bridge_seconds' => $bridge,
            'credited_seconds' => $direct + $bridge,
            'sessions' => $span['sessions'],
            'bridges' => $span['bridges'],
            'applications' => $applications,
        ];
    }

    /** @param array<string,mixed> $session */
    private function manualEvent(string $deviceId, array $session): array
    {
        $fingerprint = implode('|', [self::PROJECTION_VERSION, $deviceId, 'manual', $session['id'], $session['started_at']->toISOString(), $session['ended_at']->toISOString()]);
        return [
            'id' => hash('sha256', $fingerprint),
            'device_id' => $deviceId,
            'project_id' => $session['project_id'],
            'event_kind' => 'manual',
            'context_key' => 'manual:'.$session['id'],
            'started_at' => $session['started_at'],
            'ended_at' => $session['ended_at'],
            'direct_seconds' => (int) $session['duration_seconds'],
            'bridge_seconds' => 0,
            'credited_seconds' => (int) $session['duration_seconds'],
            'sessions' => [$session],
            'bridges' => [],
            'applications' => [],
        ];
    }

    private function decision(string $projectId, string $state, CarbonImmutable $at, string $reason, int $directSinceLastBridge, ?int $gapSeconds = null, array $interruptedProjects = []): array
    {
        return [
            'project_id'=>$projectId,
            'state'=>$state,
            'at'=>$at,
            'reason'=>$reason,
            'direct_since_last_bridge_seconds'=>$directSinceLastBridge,
            'gap_seconds'=>$gapSeconds,
            'interrupted_project_ids'=>$interruptedProjects,
        ];
    }
}

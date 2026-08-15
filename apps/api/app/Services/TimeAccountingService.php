<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TimeAccountingService
{
    /**
     * @param Collection<int, object> $sessions Objects exposing started_at and ended_at.
     * @return array{effort_seconds:int, elapsed_coverage_seconds:int, concurrent_effort_seconds:int}
     */
    public function summarize(Collection $sessions, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $effort = 0;
        $intervals = [];

        foreach ($sessions as $session) {
            $startedAt = CarbonImmutable::parse($session->started_at);
            $endedAt = CarbonImmutable::parse($session->ended_at);

            $start = $startedAt->greaterThan($rangeStart) ? $startedAt : $rangeStart;
            $end = $endedAt->lessThan($rangeEnd) ? $endedAt : $rangeEnd;

            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            $seconds = $start->diffInSeconds($end);
            $effort += $seconds;
            $intervals[] = [$start, $end];
        }

        $coverage = $this->unionSeconds($intervals);

        return [
            'effort_seconds' => $effort,
            'elapsed_coverage_seconds' => $coverage,
            'concurrent_effort_seconds' => max(0, $effort - $coverage),
        ];
    }

    /** @param array<int, array{0:CarbonImmutable,1:CarbonImmutable}> $intervals */
    private function unionSeconds(array $intervals): int
    {
        if ($intervals === []) {
            return 0;
        }

        usort($intervals, static fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());

        [$currentStart, $currentEnd] = $intervals[0];
        $total = 0;

        foreach (array_slice($intervals, 1) as [$start, $end]) {
            if ($start->lessThanOrEqualTo($currentEnd)) {
                if ($end->greaterThan($currentEnd)) {
                    $currentEnd = $end;
                }
                continue;
            }

            $total += $currentStart->diffInSeconds($currentEnd);
            $currentStart = $start;
            $currentEnd = $end;
        }

        return $total + $currentStart->diffInSeconds($currentEnd);
    }
}

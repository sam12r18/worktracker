<?php

namespace Tests\Unit;

use App\Services\WorkEventContextNormalizer;
use App\Services\WorkEventProjectionService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class WorkEventProjectionServiceTest extends TestCase
{
    private WorkEventProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkEventProjectionService(new WorkEventContextNormalizer());
    }

    public function test_same_project_across_apps_is_one_event_without_double_counting(): void
    {
        $projection = $this->service->projectRows(collect([
            $this->row('a1', 'A', '10:00:00', '10:10:00', 'phpstorm64', 'Ketabnow – README.md'),
            $this->row('a2', 'A', '10:10:00', '10:12:00', 'chrome', 'Ketabnow - Google Chrome'),
            $this->row('a3', 'A', '10:12:00', '10:20:00', 'phpstorm64', 'Ketabnow – Sync.php'),
        ]));

        $events = array_values(array_filter($projection['events'], fn (array $event) => $event['project_id'] === 'A'));
        $this->assertCount(1, $events);
        $this->assertSame(1200, $events[0]['direct_seconds']);
        $this->assertSame(0, $events[0]['bridge_seconds']);
        $this->assertSame(1200, $events[0]['credited_seconds']);
        $this->assertSame(['phpstorm64', 'chrome'], $events[0]['applications']);
    }

    public function test_plugin_context_stabilizes_unknown_phpstorm_sessions_across_file_titles(): void
    {
        $ide = [
            'protocol_version' => 1,
            'project_name' => 'WorkTracker',
            'project_path' => 'I:\\worktracker',
            'execution_mode' => 'idle',
        ];
        $first = $this->row('u1', null, '10:00:00', '10:05:00', 'phpstorm64', 'WorkTracker – README.md');
        $second = $this->row('u2', null, '10:05:00', '10:10:00', 'phpstorm64', 'WorkTracker – SyncEngine.cs');
        $first['ide_context'] = $ide;
        $second['ide_context'] = $ide;

        $projection = $this->service->projectRows(collect([$first, $second]));
        $events = array_values(array_filter($projection['events'], fn (array $event) => $event['event_kind'] === 'unknown_foreground'));

        $this->assertCount(1, $events);
        $this->assertSame(600, $events[0]['direct_seconds']);
        $this->assertStringContainsString('ide:phpstorm:', $events[0]['context_key']);
    }

    public function test_mutual_bridge_is_calculated_independently_per_project(): void
    {
        $projection = $this->service->projectRows(collect([
            $this->row('a1', 'A', '10:00:00', '10:10:00', 'phpstorm64'),
            $this->row('b1', 'B', '10:10:00', '10:11:00', 'chrome'),
            $this->row('a2', 'A', '10:11:00', '10:12:00', 'phpstorm64'),
            $this->row('b2', 'B', '10:12:00', '10:20:00', 'phpstorm64'),
            $this->row('a3', 'A', '10:20:00', '10:30:00', 'phpstorm64'),
        ]));

        $aEvents = array_values(array_filter($projection['events'], fn (array $event) => $event['project_id'] === 'A'));
        $bEvents = array_values(array_filter($projection['events'], fn (array $event) => $event['project_id'] === 'B'));

        $this->assertSame(1260, array_sum(array_column($aEvents, 'direct_seconds')));
        $this->assertSame(60, array_sum(array_column($aEvents, 'bridge_seconds')));
        $this->assertSame(1320, array_sum(array_column($aEvents, 'credited_seconds')));

        $this->assertSame(540, array_sum(array_column($bEvents, 'direct_seconds')));
        $this->assertSame(60, array_sum(array_column($bEvents, 'bridge_seconds')));
        $this->assertSame(600, array_sum(array_column($bEvents, 'credited_seconds')));

        $this->assertSame(1920, array_sum(array_column($projection['events'], 'credited_seconds')));
    }

    public function test_initial_anchor_requires_sixty_seconds(): void
    {
        $projection = $this->service->projectRows(collect([
            $this->row('a1', 'A', '10:00:00', '10:00:59', 'phpstorm64'),
            $this->row('b1', 'B', '10:00:59', '10:01:29', 'chrome'),
            $this->row('a2', 'A', '10:01:29', '10:02:29', 'phpstorm64'),
        ]));

        $aEvents = array_values(array_filter($projection['events'], fn (array $event) => $event['project_id'] === 'A'));
        $this->assertCount(2, $aEvents);
        $this->assertSame(0, array_sum(array_column($aEvents, 'bridge_seconds')));
    }

    public function test_rearm_is_per_project_and_requires_one_hundred_twenty_direct_seconds(): void
    {
        $projection = $this->service->projectRows(collect([
            $this->row('a1', 'A', '10:00:00', '10:02:00'),
            $this->row('b1', 'B', '10:02:00', '10:02:30'),
            $this->row('a2', 'A', '10:02:30', '10:03:30'),
            $this->row('b2', 'B', '10:03:30', '10:04:00'),
            $this->row('a3', 'A', '10:04:00', '10:05:00'),
        ]));

        $aEvents = array_values(array_filter($projection['events'], fn (array $event) => $event['project_id'] === 'A'));
        $this->assertCount(2, $aEvents, 'Second A interruption must not bridge after only 60s of re-arm direct work.');
        $this->assertSame(30, array_sum(array_column($aEvents, 'bridge_seconds')));
    }

    public function test_gap_longer_than_two_minutes_never_bridges(): void
    {
        $projection = $this->service->projectRows(collect([
            $this->row('a1', 'A', '10:00:00', '10:10:00'),
            $this->row('b1', 'B', '10:10:00', '10:12:01'),
            $this->row('a2', 'A', '10:12:01', '10:20:00'),
        ]));

        $aEvents = array_values(array_filter($projection['events'], fn (array $event) => $event['project_id'] === 'A'));
        $this->assertCount(2, $aEvents);
        $this->assertSame(0, array_sum(array_column($aEvents, 'bridge_seconds')));
    }

    /** @return array<string,mixed> */
    private function row(
        string $id,
        ?string $projectId,
        string $start,
        string $end,
        string $process = 'phpstorm64',
        string $title = 'Work',
    ): array {
        $startedAt = CarbonImmutable::parse('2026-08-16 '.$start, 'Asia/Tehran');
        $endedAt = CarbonImmutable::parse('2026-08-16 '.$end, 'Asia/Tehran');

        return [
            'id'=>$id,
            'device_id'=>'11111111-1111-1111-1111-111111111111',
            'project_id'=>$projectId,
            'activity_type_id'=>null,
            'source'=>'auto_foreground',
            'process_name'=>$process,
            'window_title'=>$title,
            'started_at'=>$startedAt,
            'ended_at'=>$endedAt,
            'duration_seconds'=>$startedAt->diffInSeconds($endedAt),
        ];
    }
}

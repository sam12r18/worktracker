@extends('layouts.worktracker',['title'=>'WorkTracker — گزارش‌ها'])
@section('content')
@php
    $fmt = fn($seconds) => sprintf('%02d:%02d', intdiv((int)$seconds,3600), intdiv((int)$seconds%3600,60));
    $fmtLong = fn($seconds) => sprintf('%02d:%02d:%02d', intdiv((int)$seconds,3600), intdiv((int)$seconds%3600,60), (int)$seconds%60);
    $projectMap = $projects->pluck('name','id');
@endphp

<x-worktracker.page-header title="گزارش زمانی" subtitle="گزارش alpha.7.3 از Work Event Projection استفاده می‌کند؛ Effort شامل Direct + Continuity Bridge است.">
    <x-slot:actions>
        <form method="get" class="wt-row">
            <select name="preset">
                <option value="day" @selected($preset==='day')>روز</option>
                <option value="week" @selected($preset==='week')>هفته</option>
                <option value="month" @selected($preset==='month')>ماه</option>
                <option value="custom" @selected($preset==='custom')>دلخواه</option>
            </select>
            <input type="date" name="from" value="{{ request('from',$from->toDateString()) }}">
            <input type="date" name="to" value="{{ request('to',$to->subDay()->toDateString()) }}">
            <select name="project_id">
                <option value="">همه پروژه‌ها</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected($projectId===$project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            <button class="wt-btn-primary">اعمال</button>
        </form>
    </x-slot:actions>
</x-worktracker.page-header>

<div class="wt-cards">
    <x-worktracker.metric label="Effort" :value="$fmt($report['summary']['effort_seconds'])" hint="Direct + Bridge"/>
    <x-worktracker.metric label="Direct" :value="$fmt($report['summary']['raw_effort_seconds'])" hint="Raw Activity Effort"/>
    <x-worktracker.metric label="Bridge" :value="$fmt($report['summary']['continuity_bridge_seconds'])" :hint="$report['summary']['bridges_count'].' Continuity Bridge'"/>
    <x-worktracker.metric label="Coverage" :value="$fmt($report['summary']['elapsed_coverage_seconds'])"/>
    <x-worktracker.metric label="Concurrent" :value="$fmt($report['summary']['concurrent_effort_seconds'])"/>
    <x-worktracker.metric label="Work Event" :value="$report['summary']['work_events_count']" :hint="$report['sessions_count'].' Raw Activity'"/>
</div>

<div class="wt-grid">
    <div>
        <x-worktracker.panel title="روند روزانه">
            <x-worktracker.table>
                <thead><tr><th>تاریخ</th><th>Effort</th><th>Direct</th><th>Bridge</th><th>Coverage</th><th>Concurrent</th><th>Event</th></tr></thead>
                <tbody>
                @foreach($report['days'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td><strong>{{ $fmt($day['effort_seconds']) }}</strong></td>
                        <td>{{ $fmt($day['raw_effort_seconds']) }}</td>
                        <td>{{ $fmt($day['continuity_bridge_seconds']) }}</td>
                        <td>{{ $fmt($day['elapsed_coverage_seconds']) }}</td>
                        <td>{{ $fmt($day['concurrent_effort_seconds']) }}</td>
                        <td>{{ $day['work_events_count'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </x-worktracker.table>
        </x-worktracker.panel>
    </div>
    <div>
        <x-worktracker.panel title="انواع فعالیت — زمان مستقیم">
            <p class="wt-muted">Continuity Bridge نوع فعالیت مستقل ندارد و در این نمودار توزیع نمی‌شود؛ برای شفافیت جداگانه در Metricهای بالا نمایش داده می‌شود.</p>
            @foreach($report['activity_types'] as $type)
                <div style="margin-bottom:12px">
                    <div class="wt-row" style="justify-content:space-between">
                        <strong>{{ $type['name'] }}</strong><span>{{ $fmt($type['effort_seconds']) }}</span>
                    </div>
                    <div class="wt-kpi-bar"><i style="width:{{ min(100,($report['summary']['raw_effort_seconds']?($type['effort_seconds']/$report['summary']['raw_effort_seconds']*100):0)) }}%"></i></div>
                </div>
            @endforeach
        </x-worktracker.panel>
    </div>
</div>

<x-worktracker.panel title="Work Event Projection">
    <div class="wt-row" style="justify-content:space-between;align-items:center;margin-bottom:10px">
        <p class="wt-muted" style="margin:0">تغییر فایل/تب در یک پروژه می‌تواند در یک Event تجمیع شود. Bridgeها فقط وقتی بازگشت معتبر به همان پروژه وجود داشته باشد اضافه می‌شوند.</p>
        <a class="wt-btn" href="{{ route('worktracker.work-events.index',['date'=>$from->timezone($timezone)->toDateString()]) }}">Audit رویدادها</a>
    </div>
    <x-worktracker.table>
        <thead><tr><th>زمان</th><th>پروژه</th><th>Direct</th><th>Bridge</th><th>Credited</th><th>Segments</th><th>برنامه‌ها</th></tr></thead>
        <tbody>
        @forelse(array_slice($workEvents,0,40) as $event)
            <tr>
                <td style="white-space:nowrap">{{ $event['started_at']->timezone($timezone)->format('m/d H:i:s') }}–{{ $event['ended_at']->timezone($timezone)->format('H:i:s') }}</td>
                <td>{{ $event['project_id'] ? ($projectMap[$event['project_id']] ?? $event['project_id']) : 'تشخیص‌داده‌نشده' }}</td>
                <td>{{ $fmtLong($event['direct_seconds']) }}</td>
                <td>{{ $fmtLong($event['bridge_seconds']) }}</td>
                <td><strong>{{ $fmtLong($event['credited_seconds']) }}</strong></td>
                <td>{{ count($event['sessions']) }}</td>
                <td>{{ implode(' + ', $event['applications']) ?: 'ثبت دستی' }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-worktracker.empty title="Work Event برای این بازه وجود ندارد"/></td></tr>
        @endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

<x-worktracker.panel title="Timeline خام — برای Audit">
    <div class="wt-table-wrap">
        <div class="wt-timeline">
            <div class="wt-timeline-axis">@for($hour=0;$hour<24;$hour+=2)<span>{{ sprintf('%02d:00',$hour) }}</span>@endfor</div>
            @forelse($sessions as $session)
                @php
                    $day = $session->started_at->timezone($timezone)->startOfDay();
                    $start = max(0,$day->diffInSeconds($session->started_at->timezone($timezone),false));
                    $end = min(86400,$day->diffInSeconds($session->ended_at->timezone($timezone),false));
                    $left = 100*($start/86400);
                    $width = max(.25,100*(max(1,$end-$start)/86400));
                @endphp
                <div class="wt-timeline-row">
                    <div class="wt-timeline-label">{{ $session->project?->name ?? 'Unknown' }} · {{ $session->activityType?->name ?? $session->source }}</div>
                    <div class="wt-timeline-track">
                        <div class="wt-timeline-bar" style="left:{{ $left }}%;width:{{ $width }}%" title="{{ $session->started_at->timezone($timezone)->format('H:i') }}–{{ $session->ended_at->timezone($timezone)->format('H:i') }} · {{ $session->note ?: $session->window_title }}">{{ $session->started_at->timezone($timezone)->format('H:i') }}</div>
                    </div>
                </div>
            @empty
                <x-worktracker.empty title="Timeline خالی است"/>
            @endforelse
        </div>
    </div>
</x-worktracker.panel>
@endsection

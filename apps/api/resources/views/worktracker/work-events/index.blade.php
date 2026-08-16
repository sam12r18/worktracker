@extends('layouts.worktracker',['title'=>'WorkTracker — رویدادهای کاری'])
@section('content')
@php
    $fmt = fn($seconds) => sprintf('%02d:%02d:%02d', intdiv((int)$seconds,3600), intdiv((int)$seconds%3600,60), (int)$seconds%60);
@endphp
<x-worktracker.page-header title="رویدادهای کاری" subtitle="Projection سمت Laravel از Raw Activityها؛ Bridgeها قابل Audit هستند و Raw Sessionها دست‌نخورده می‌مانند.">
    <x-slot:actions>
        <form method="get" class="wt-row">
            <input type="date" name="date" value="{{ $date }}">
            <select name="device_id">
                <option value="">همه دستگاه‌ها</option>
                @foreach($devices as $device)
                    <option value="{{ $device->id }}" @selected((string)$deviceId===(string)$device->id)>{{ $device->operator_label ?: $device->name }}</option>
                @endforeach
            </select>
            <select name="project_id">
                <option value="">همه پروژه‌ها</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((string)$projectId===(string)$project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            <button class="wt-btn-primary">اعمال</button>
        </form>
    </x-slot:actions>
</x-worktracker.page-header>

<div class="wt-cards">
    <x-worktracker.metric label="Credited Effort" :value="$fmt($summary['credited_seconds'])" hint="Direct + Continuity Bridge"/>
    <x-worktracker.metric label="Direct" :value="$fmt($summary['direct_seconds'])" hint="Raw foreground/manual effort"/>
    <x-worktracker.metric label="Bridge" :value="$fmt($summary['bridge_seconds'])" :hint="$summary['bridges_count'].' پل تداوم'"/>
    <x-worktracker.metric label="Work Event" :value="$summary['events_count']" :hint="$summary['segments_count'].' Raw Segment'"/>
</div>

<x-worktracker.panel title="بازسازی Projection">
    @if($rawSessionsCount > 0 && $summary['events_count'] === 0)
        <div class="wt-flash" style="margin-bottom:10px">
            برای این روز {{ $rawSessionsCount }} Raw Activity وجود دارد ولی Projection هنوز ساخته نشده است. یک بار «بازسازی مجدد» را اجرا کن.
        </div>
    @endif
    <div class="wt-row" style="justify-content:space-between;align-items:center">
        <p class="wt-muted" style="margin:0">بازسازی فقط داده مشتق‌شده را از Raw Activityهای همان روز دوباره محاسبه می‌کند؛ هیچ Activity خامی حذف یا ویرایش نمی‌شود.</p>
        <form method="post" action="{{ route('worktracker.work-events.rebuild') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="device_id" value="{{ $deviceId }}">
            <button class="wt-btn">بازسازی مجدد</button>
        </form>
    </div>
</x-worktracker.panel>

<x-worktracker.panel title="Work Eventها">
    <x-worktracker.table>
        <thead>
            <tr><th>زمان</th><th>پروژه</th><th>دستگاه</th><th>Direct</th><th>Bridge</th><th>Credited</th><th>Segments</th><th>جزئیات</th></tr>
        </thead>
        <tbody>
        @forelse($events as $event)
            <tr>
                <td style="white-space:nowrap">{{ $event->started_at->timezone($timezone)->format('H:i:s') }}–{{ $event->ended_at->timezone($timezone)->format('H:i:s') }}</td>
                <td><strong>{{ $event->project?->name ?? ($event->event_kind==='manual' ? 'بدون پروژه' : 'تشخیص‌داده‌نشده') }}</strong><br><small class="wt-muted">{{ $event->event_kind }}</small></td>
                <td>{{ $event->device?->operator_label ?: $event->device?->name }}</td>
                <td>{{ $fmt($event->direct_seconds) }}</td>
                <td>{{ $fmt($event->bridge_seconds) }}</td>
                <td><strong>{{ $fmt($event->credited_seconds) }}</strong></td>
                <td>{{ $event->segment_count }}</td>
                <td style="min-width:300px">
                    <details>
                        <summary>{{ $event->bridge_count ? $event->bridge_count.' Bridge · ' : '' }}{{ implode(' + ', $event->applications ?? []) ?: 'ثبت دستی' }}</summary>
                        <div style="margin-top:8px">
                            @foreach($event->segments as $segment)
                                <div class="wt-muted" style="margin-bottom:4px">
                                    {{ $segment->started_at->timezone($timezone)->format('H:i:s') }}–{{ $segment->ended_at->timezone($timezone)->format('H:i:s') }} ·
                                    {{ $segment->activitySession?->process_name ?: $segment->activitySession?->source }} ·
                                    {{ \Illuminate\Support\Str::limit($segment->activitySession?->window_title,80) }}
                                    @if($segment->activitySession?->activityType)
                                        · {{ $segment->activitySession->activityType->name }}
                                        @if($segment->activitySession->activity_type_source)
                                            <span class="wt-badge">{{ $segment->activitySession->activity_type_source }} {{ $segment->activitySession->activity_type_confidence !== null ? number_format((float)$segment->activitySession->activity_type_confidence,2) : '' }}</span>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                            @foreach($event->bridges as $bridge)
                                @php
                                    $names = collect($bridge->interrupted_project_ids ?? [])->map(fn($id)=>$projectNames[$id] ?? $id)->implode('، ');
                                @endphp
                                <div class="wt-badge" style="margin-top:6px">
                                    Bridge {{ $fmt($bridge->duration_seconds) }} · {{ $bridge->started_at->timezone($timezone)->format('H:i:s') }}–{{ $bridge->ended_at->timezone($timezone)->format('H:i:s') }}
                                    @if($names) · وقفه: {{ $names }} @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><x-worktracker.empty title="برای این فیلتر Work Event وجود ندارد"/></td></tr>
        @endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>
@endsection

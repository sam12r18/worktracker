@extends('layouts.worktracker', ['title' => 'WorkTracker — لاگ و عیب‌یابی'])

@section('content')
<x-worktracker.page-header title="لاگ و عیب‌یابی" subtitle="بررسی زنجیره Agent → API → Sync با Correlation ID مشترک.">
    <x-slot:actions>
        <a class="wt-btn" href="{{ route('worktracker.diagnostics.index') }}">بروزرسانی</a>
    </x-slot:actions>
</x-worktracker.page-header>

<div class="wt-grid">
    <x-worktracker.panel title="وضعیت دستگاه‌ها">
        @forelse($devices as $device)
            <div class="wt-form-card" style="margin-bottom:10px">
                <div class="wt-row" style="justify-content:space-between">
                    <strong>{{ $device->name }}</strong>
                    @if($device->last_sync_error)
                        <x-worktracker.badge tone="warning">آخرین Sync خطا داشته</x-worktracker.badge>
                    @else
                        <x-worktracker.badge tone="success">بدون خطای ثبت‌شده</x-worktracker.badge>
                    @endif
                </div>
                <div class="wt-muted wt-break">{{ $device->id }}</div>
                <div class="wt-row wt-muted" style="margin-top:6px">
                    <span>نسخه: {{ $device->app_version ?: '—' }}</span>
                    <span>Push: {{ $device->last_sync_pushed ?? 0 }}</span>
                    <span>Pull: {{ $device->last_sync_pulled ?? 0 }}</span>
                    <span>آخرین موفق: {{ $device->last_sync_succeeded_at ?: '—' }}</span>
                </div>
                @if($device->last_sync_error)
                    <pre style="margin-top:8px;white-space:pre-wrap">{{ $device->last_sync_error }}</pre>
                @endif
            </div>
        @empty
            <x-worktracker.empty title="دستگاهی ثبت نشده است"/>
        @endforelse
    </x-worktracker.panel>

    <x-worktracker.panel title="راهنمای تشخیص Sync">
        <div class="wt-stack">
            <div><strong>Correlation ID</strong><div class="wt-muted">یک شناسه مشترک بین لاگ Agent و Laravel است. با جستجوی همان شناسه می‌توان درخواست را دو طرف دنبال کرد.</div></div>
            <div><strong>Pending / Due / Delayed</strong><div class="wt-muted">Pending کل Outbox است. Due همین الآن قابل ارسال است و Delayed به‌دلیل backoff تا زمان Retry منتظر مانده است.</div></div>
            <div><strong>Validation failure</strong><div class="wt-muted">اگر API یک Activity را رد کند، نام فیلد و خطای Validation بدون Token در فایل worktracker-sync ثبت می‌شود.</div></div>
            <div><strong>حریم داده</strong><div class="wt-muted">Authorization و Sanctum Token عمداً در لاگ Sync نوشته نمی‌شوند.</div></div>
        </div>
    </x-worktracker.panel>
</div>

<x-worktracker.panel title="آخرین لاگ‌های Sync سرور">
    @if($logFiles)
        <div class="wt-muted" style="margin-bottom:8px">فایل‌ها: {{ implode('، ', $logFiles) }}</div>
    @endif
    @if($logLines)
        <pre style="direction:ltr;text-align:left;white-space:pre-wrap;max-height:520px;overflow:auto">{{ implode("\n", $logLines) }}</pre>
    @else
        <x-worktracker.empty title="هنوز لاگ Sync سمت سرور ثبت نشده"/>
    @endif
</x-worktracker.panel>
@endsection

@extends('layouts.worktracker', ['title' => 'WorkTracker — تعارض‌ها'])

@section('content')
<x-worktracker.page-header title="Conflict Resolution" subtitle="هیچ Activity زمان‌دار بدون تصمیم صریح بازنویسی نمی‌شود.">
    <x-slot:actions><a class="wt-btn" href="{{ route('worktracker.dashboard') }}">بازگشت</a></x-slot:actions>
</x-worktracker.page-header>

@forelse($conflicts as $c)
<x-worktracker.panel>
    <div class="wt-row"><strong>{{ $c->entity_type }}</strong><span class="wt-muted wt-break">{{ $c->entity_id }}</span><span class="wt-muted">{{ $c->device?->operator_label ?: $c->device?->name }}</span><span class="wt-muted">{{ $c->status }}</span></div>
    <div class="wt-row wt-muted" style="margin:8px 0"><span class="wt-warn">Client v{{ $c->client_version }}</span><span class="wt-ok">Server v{{ $c->server_version }}</span><span>{{ $c->reason }}</span></div>
    <details><summary>مقایسه payload</summary><div class="wt-grid" style="margin-top:10px"><div><b>Client</b><pre>{{ json_encode($c->client_payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div><div><b>Server</b><pre>{{ json_encode($c->server_payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div></div></details>
    @if($c->status==='open')<form method="post" action="{{ route('worktracker.conflicts.resolve',$c) }}" class="wt-row" style="margin-top:10px">@csrf<button name="resolution" value="keep_server">حفظ نسخه سرور</button><button name="resolution" value="accept_client">پذیرش نسخه Client</button></form>@else<div class="wt-muted">تصمیم: {{ $c->resolution }} · {{ $c->resolved_at }}</div>@endif
</x-worktracker.panel>
@empty<x-worktracker.panel>تعارضی وجود ندارد.</x-worktracker.panel>@endforelse

<div>{{ $conflicts->links() }}</div>
@endsection

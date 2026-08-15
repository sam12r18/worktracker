@extends('layouts.worktracker', ['title' => 'WorkTracker — مشتری‌ها'])

@section('content')
<x-worktracker.page-header title="مدیریت مشتری‌ها" subtitle="Master Data مشتری، ضریب مالی، وضعیت و پروژه‌های مرتبط.">
    <x-slot:actions><a class="wt-btn" href="{{ route('worktracker.projects.index') }}">مدیریت پروژه‌ها</a></x-slot:actions>
</x-worktracker.page-header>

<div class="wt-grid" style="margin-top:16px">
    <div>
        <x-worktracker.panel title="مشتری‌ها">
            <form method="get" class="wt-row" style="margin-bottom:12px">
                <input class="wt-field-grow" name="q" value="{{ $filters['q'] }}" placeholder="نام یا شرکت">
                <select name="status"><option value="all" @selected($filters['status']==='all')>همه</option><option value="active" @selected($filters['status']==='active')>فعال</option><option value="inactive" @selected($filters['status']==='inactive')>غیرفعال</option></select>
                <button>فیلتر</button>
            </form>
            <x-worktracker.table>
                <thead><tr><th>مشتری</th><th>پروژه‌ها</th><th>ضریب</th><th>واحد پول</th><th>وضعیت</th></tr></thead>
                <tbody>
                @forelse($customers as $c)
                    <tr>
                        <td><a href="{{ route('worktracker.customers.show',$c) }}"><strong>{{ $c->name }}</strong></a><div class="wt-muted">{{ $c->company_name ?: '—' }}</div></td>
                        <td>{{ $c->projects_count }}</td><td>{{ $c->rate_multiplier }}</td><td>{{ $c->currency }}</td>
                        <td>@if($c->is_active)<x-worktracker.badge tone="success">فعال</x-worktracker.badge>@else<x-worktracker.badge tone="danger">غیرفعال</x-worktracker.badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-worktracker.empty title="هنوز مشتری ثبت نشده"/></td></tr>
                @endforelse
                </tbody>
            </x-worktracker.table>
            <div style="margin-top:12px">{{ $customers->links() }}</div>
        </x-worktracker.panel>
    </div>

    <div>
        <x-worktracker.panel title="مشتری جدید">
            <form method="post" action="{{ route('worktracker.customers.store') }}" class="wt-form">
                @csrf
                <label>نام مشتری<input name="name" value="{{ old('name') }}" required></label>
                <label>نام شرکت<input name="company_name" value="{{ old('company_name') }}"></label>
                <label>ضریب مشتری<input name="rate_multiplier" type="number" step="0.0001" min="0" max="100" value="{{ old('rate_multiplier','1.0000') }}" required></label>
                <label>واحد پول<input name="currency" value="{{ old('currency','IRT') }}" maxlength="8" required></label>
                <label>شروع اعتبار<input type="datetime-local" name="effective_from" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                <label>یادداشت مالی<textarea name="billing_notes" placeholder="شرایط قرارداد، نحوه صورتحساب و ...">{{ old('billing_notes') }}</textarea></label>
                <label class="wt-check"><input type="checkbox" name="is_active" value="1" checked> مشتری فعال است</label>
                <button class="wt-btn-primary">ثبت مشتری</button>
            </form>
        </x-worktracker.panel>
    </div>
</div>
@endsection

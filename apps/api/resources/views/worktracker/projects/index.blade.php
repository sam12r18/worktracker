@extends('layouts.worktracker', ['title' => 'WorkTracker — پروژه‌ها'])

@section('content')
<x-worktracker.page-header title="مدیریت پروژه‌ها" subtitle="تعریف پروژه، اتصال به مشتری، ساختار والد/فرزند و تنظیمات اولیه قیمت‌گذاری.">
    <x-slot:actions><a class="wt-btn" href="{{ route('worktracker.customers.index') }}">مدیریت مشتری‌ها</a></x-slot:actions>
</x-worktracker.page-header>

<div class="wt-grid" style="margin-top:16px">
    <div>
        <x-worktracker.panel title="پروژه‌ها">
            <form method="get" class="wt-row" style="margin-bottom:12px">
                <input class="wt-field-grow" name="q" value="{{ $filters['q'] }}" placeholder="جستجو نام یا کد">
                <select name="customer_id"><option value="">همه مشتری‌ها</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($filters['customer_id']===$c->id)>{{ $c->name }}</option>@endforeach</select>
                <select name="status">
                    @foreach(['active'=>'فعال','paused'=>'متوقف','completed'=>'تکمیل','archived'=>'آرشیو','all'=>'همه'] as $value=>$label)
                        <option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button>فیلتر</button>
            </form>

            <x-worktracker.table>
                <thead><tr><th>پروژه</th><th>مشتری</th><th>ساختار</th><th>Rule</th><th>Task</th><th>ضریب</th><th>وضعیت</th></tr></thead>
                <tbody>
                @forelse($projects as $p)
                    <tr>
                        <td><a href="{{ route('worktracker.projects.show',$p) }}"><strong>{{ $p->name }}</strong></a><div class="wt-muted">{{ $p->code ?: 'بدون کد' }}</div></td>
                        <td>{{ $p->customer?->name ?: '—' }}</td>
                        <td>{{ $p->parent?->name ?: 'ریشه' }}</td>
                        <td>{{ $p->rules_count }}</td>
                        <td>{{ $p->tasks_count }}</td>
                        <td>{{ $p->rate_multiplier }}</td>
                        <td>@if($p->is_archived)<x-worktracker.badge tone="danger">آرشیو</x-worktracker.badge>@elseif($p->status==='completed')<x-worktracker.badge tone="success">تکمیل</x-worktracker.badge>@elseif($p->status==='paused')<x-worktracker.badge tone="warning">متوقف</x-worktracker.badge>@else<x-worktracker.badge tone="primary">فعال</x-worktracker.badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-worktracker.empty title="پروژه‌ای پیدا نشد"/></td></tr>
                @endforelse
                </tbody>
            </x-worktracker.table>
            <div style="margin-top:12px">{{ $projects->links() }}</div>
        </x-worktracker.panel>
    </div>

    <div>
        <x-worktracker.panel title="پروژه جدید">
            <form method="post" action="{{ route('worktracker.projects.store') }}" class="wt-form">
                @csrf
                <label>نام پروژه<input name="name" value="{{ old('name') }}" required maxlength="180"></label>
                <label>کد پروژه<input name="code" value="{{ old('code') }}" maxlength="80" placeholder="اختیاری"></label>
                <label>
                    <span class="wt-help-inline-title">مشتری
                        <x-worktracker.help title="اتصال پروژه به مشتری"><p>مالک تجاری پروژه را مشخص می‌کند و ضریب مشتری در Billing روی این پروژه اعمال می‌شود. پروژه می‌تواند موقتاً بدون مشتری هم ساخته شود.</p></x-worktracker.help>
                    </span>
                    <select name="customer_id"><option value="">بدون مشتری</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id')===$c->id)>{{ $c->name }}</option>@endforeach</select>
                </label>
                <label>پروژه والد<select name="parent_id"><option value="">پروژه ریشه</option>@foreach($parents as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
                <label>وضعیت<select name="status"><option value="active">فعال</option><option value="paused">متوقف</option><option value="completed">تکمیل‌شده</option></select></label>
                <label>رنگ<input name="color" type="color" value="{{ old('color','#4d7fff') }}"></label>
                <label>
                    <span class="wt-help-inline-title">ضریب پروژه
                        <x-worktracker.help title="ضریب پروژه"><p>نرخ پایه × ضریب مشتری × ضریب پروژه. مقدار 1.0000 یعنی پروژه نرخ را تغییر نمی‌دهد.</p></x-worktracker.help>
                    </span>
                    <input name="rate_multiplier" type="number" step="0.0001" min="0" max="100" value="{{ old('rate_multiplier','1.0000') }}" required>
                </label>
                <label class="wt-check"><input type="checkbox" name="is_billable_default" value="1" checked> پروژه به‌صورت پیش‌فرض Billable باشد</label>
                <label>شروع اعتبار قیمت‌گذاری<input type="datetime-local" name="effective_from" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                <button class="wt-btn-primary">ساخت پروژه</button>
            </form>
        </x-worktracker.panel>
    </div>
</div>
@endsection

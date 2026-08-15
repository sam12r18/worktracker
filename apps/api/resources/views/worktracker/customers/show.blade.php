@extends('layouts.worktracker', ['title' => 'WorkTracker — '.$customer->name])

@section('content')
<x-worktracker.page-header :title="$customer->name" :subtitle="$customer->company_name ?: 'مشتری مستقل'">
    <x-slot:actions><a class="wt-btn" href="{{ route('worktracker.customers.index') }}">همه مشتری‌ها</a><a class="wt-btn-primary wt-btn" href="{{ route('worktracker.projects.index',['customer_id'=>$customer->id]) }}">پروژه‌های مشتری</a></x-slot:actions>
</x-worktracker.page-header>

<div class="wt-cards">
    <x-worktracker.metric label="پروژه" :value="(string)$projects->count()" hint="پروژه‌های متصل"/>
    <x-worktracker.metric label="فاکتور" :value="(string)($invoiceStats->invoices_count ?? 0)" hint="کل اسناد این مشتری"/>
    <x-worktracker.metric label="مجموع فاکتورها" :value="number_format($invoiceStats->total_minor ?? 0).' '.$customer->currency" hint="جمع ثبت‌شده در Invoiceها"/>
    <x-worktracker.metric label="ضریب فعلی" :value="(string)$customer->rate_multiplier" :hint="$customer->is_active ? 'فعال' : 'غیرفعال'"/>
</div>

<div class="wt-grid-2">
    <x-worktracker.panel title="مشخصات و تنظیمات مالی">
        <form method="post" action="{{ route('worktracker.customers.update',$customer) }}" class="wt-form">@csrf
            <label>نام<input name="name" value="{{ old('name',$customer->name) }}" required></label>
            <label>شرکت<input name="company_name" value="{{ old('company_name',$customer->company_name) }}"></label>
            <label>
                <span class="wt-help-inline-title">ضریب مشتری<x-worktracker.help title="ضریب مشتری"><p>این ضریب در نرخ پایه Activity و ضریب پروژه ضرب می‌شود. تغییر آن با تاریخ اعمال در History ثبت می‌شود.</p></x-worktracker.help></span>
                <input name="rate_multiplier" type="number" step="0.0001" min="0" max="100" value="{{ old('rate_multiplier',$customer->rate_multiplier) }}" required>
            </label>
            <label>واحد پول<input name="currency" value="{{ old('currency',$customer->currency) }}" maxlength="8" required></label>
            <label>اعمال تغییر مالی از<input type="datetime-local" name="effective_from" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <label>یادداشت مالی<textarea name="billing_notes">{{ old('billing_notes',$customer->billing_notes) }}</textarea></label>
            <label class="wt-check"><input type="checkbox" name="is_active" value="1" @checked($customer->is_active)> مشتری فعال است</label>
            <button class="wt-btn-primary">ذخیره</button>
        </form>
        <form method="post" action="{{ route('worktracker.customers.toggle',$customer) }}" style="margin-top:10px">@csrf<button class="{{ $customer->is_active ? 'wt-danger' : '' }}">{{ $customer->is_active ? 'غیرفعال‌کردن مشتری' : 'فعال‌کردن مشتری' }}</button></form>
    </x-worktracker.panel>

    <x-worktracker.panel title="تاریخچه ضریب مشتری">
        <x-worktracker.table><thead><tr><th>اعمال از</th><th>ضریب</th><th>واحد</th></tr></thead><tbody>
        @forelse($history as $row)<tr><td>{{ $row->effective_from }}</td><td>{{ $row->multiplier }}</td><td>{{ $row->currency }}</td></tr>@empty<tr><td colspan="3">—</td></tr>@endforelse
        </tbody></x-worktracker.table>
    </x-worktracker.panel>
</div>

<x-worktracker.panel title="پروژه‌های مشتری" style="margin-top:14px">
    <x-worktracker.table><thead><tr><th>پروژه</th><th>وضعیت</th><th>ضریب</th><th>Rule</th><th>Task</th></tr></thead><tbody>
    @forelse($projects as $project)
        <tr><td><a href="{{ route('worktracker.projects.show',$project) }}"><strong>{{ $project->name }}</strong></a><div class="wt-muted">{{ $project->code ?: '—' }}</div></td><td>{{ $project->status }}</td><td>{{ $project->rate_multiplier }}</td><td>{{ $project->rules_count }}</td><td>{{ $project->tasks_count }}</td></tr>
    @empty<tr><td colspan="5"><x-worktracker.empty title="این مشتری هنوز پروژه ندارد"/></td></tr>@endforelse
    </tbody></x-worktracker.table>
</x-worktracker.panel>

@if($overrides->isNotEmpty())
<x-worktracker.panel title="نرخ‌های استثنا">
    <x-worktracker.table><thead><tr><th>پروژه</th><th>فعالیت</th><th>نرخ نهایی</th><th>از</th><th>تا</th></tr></thead><tbody>
    @foreach($overrides as $override)<tr><td>{{ $override->project?->name ?: 'سطح مشتری' }}</td><td>{{ $override->activityType?->name ?: '—' }}</td><td>{{ number_format($override->hourly_rate_minor) }} {{ $override->currency }}</td><td>{{ $override->effective_from }}</td><td>{{ $override->effective_until ?: '—' }}</td></tr>@endforeach
    </tbody></x-worktracker.table>
</x-worktracker.panel>
@endif
@endsection

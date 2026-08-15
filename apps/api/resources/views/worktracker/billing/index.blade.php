@extends('layouts.worktracker', ['title' => 'WorkTracker — قیمت‌گذاری'])

@section('content')
@php($activeTypes = $types->where('is_active', true))

<x-worktracker.page-header title="قیمت‌گذاری" subtitle="نرخ مؤثر = نرخ پایه فعالیت × ضریب مشتری × ضریب پروژه؛ Override نرخ نهایی است.">
    <x-slot:actions>
        <a class="wt-btn" href="{{ route('worktracker.customers.index') }}">مشتری‌ها</a>
        <a class="wt-btn" href="{{ route('worktracker.projects.index') }}">پروژه‌ها</a>
        <a class="wt-btn" href="{{ route('worktracker.invoices.index') }}">فاکتورها</a>
    </x-slot:actions>
</x-worktracker.page-header>

<div class="wt-grid wt-grid-3" style="margin-top:16px">
    <x-worktracker.panel title="مشتری جدید">
        <p class="wt-muted">مدیریت کامل مشتری‌ها در منوی «مشتری‌ها» انجام می‌شود.</p>
        <form method="post" action="{{ route('worktracker.billing.customers.store') }}" class="wt-form">@csrf
            <label>نام<input name="name" required></label>
            <label>شرکت<input name="company_name"></label>
            <label>ضریب مشتری<input name="rate_multiplier" type="number" step="0.0001" value="1.0000" required></label>
            <label>واحد پول<input name="currency" value="IRT" required></label>
            <label>شروع اعتبار<input name="effective_from" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <button>ثبت مشتری</button>
        </form>
    </x-worktracker.panel>

    <x-worktracker.panel>
        <div class="wt-section-title"><h3 style="margin:0">نوع فعالیت جدید</h3><x-worktracker.help title="Activity Type"><p>Activity Type دسته کار مثل کدنویسی، جلسه یا تحلیل است. نرخ پایه و Billable Default در Billing از این موجودیت می‌آید.</p></x-worktracker.help></div>
        <form method="post" action="{{ route('worktracker.billing.activity-types.store') }}" class="wt-form">@csrf
            <label>کد<input name="code" placeholder="development" required></label>
            <label>عنوان<input name="name" placeholder="کدنویسی" required></label>
            <label>نرخ پایه ساعتی<input name="base_hourly_rate_minor" type="number" min="0" required></label>
            <label>واحد پول<input name="currency" value="IRT" required></label>
            <label>ترتیب نمایش<input name="sort_order" type="number" min="0" value="0"></label>
            <label>شروع اعتبار<input name="effective_from" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <label class="wt-check"><input type="checkbox" name="is_billable_default" value="1" checked> قابل صورتحساب</label>
            <label class="wt-check"><input type="checkbox" name="is_active" value="1" checked> فعال</label>
            <button>ثبت فعالیت</button>
        </form>
    </x-worktracker.panel>

    <x-worktracker.panel title="پیش‌نمایش نرخ">
        <form id="pricing-preview" class="wt-form">@csrf
            <label>پروژه<select name="project_id" required>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
            <label>فعالیت<select name="activity_type_id" required>@foreach($activeTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></label>
            <label>مدت (دقیقه)<input name="duration_minutes" type="number" value="60" min="1" required></label>
            <label>در تاریخ<input name="effective_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <button type="submit">محاسبه</button>
        </form>
        <div id="pricing-preview-result" class="wt-pricing-result">—</div>
    </x-worktracker.panel>
</div>

<x-worktracker.panel title="Activity Typeها">
    <x-worktracker.table>
        <thead><tr><th>تنظیم Activity Type</th></tr></thead>
        <tbody>
        @foreach($types as $t)
            <tr><td>
                @if($t->user_id)
                    <form method="post" action="{{ route('worktracker.billing.activity-types.update',$t->id) }}" class="wt-inline-form">@csrf
                        <label>عنوان<input name="name" value="{{ $t->name }}" required></label>
                        <span class="wt-badge">{{ $t->code }}</span>
                        <label>نرخ پایه<input name="base_hourly_rate_minor" type="number" min="0" value="{{ $t->base_hourly_rate_minor }}" required></label>
                        <label>واحد<input name="currency" value="{{ $t->currency }}" required></label>
                        <label>ترتیب<input name="sort_order" type="number" min="0" value="{{ $t->sort_order }}" required></label>
                        <label class="wt-check"><input type="checkbox" name="is_billable_default" value="1" @checked($t->is_billable_default)> Billable</label>
                        <label class="wt-check"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> فعال</label>
                        <label>اعمال از<input name="effective_from" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required></label>
                        <button>ذخیره</button>
                    </form>
                @else
                    <div class="wt-inline-form"><b>{{ $t->name }}</b><span>{{ $t->code }}</span><span>{{ number_format($t->base_hourly_rate_minor) }} {{ $t->currency }}</span><span class="wt-muted">سراسری / فقط خواندنی</span></div>
                @endif
            </td></tr>
        @endforeach
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

<x-worktracker.panel title="پروژه‌ها، مشتری و ضریب پروژه">
    <p class="wt-muted">این جدول برای اصلاح سریع مالی حفظ شده؛ مدیریت کامل پروژه از منوی «پروژه‌ها» انجام می‌شود.</p>
    <x-worktracker.table>
        <thead><tr><th>پروژه</th><th>تنظیمات مالی</th></tr></thead>
        <tbody>
        @foreach($projects as $p)
            <tr><td><a href="{{ route('worktracker.projects.show',$p) }}"><strong>{{ $p->name }}</strong></a></td><td>
                <form method="post" action="{{ route('worktracker.billing.projects.update',$p->id) }}" class="wt-inline-form">@csrf
                    <select name="customer_id"><option value="">بدون مشتری</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($p->customer_id===$c->id)>{{ $c->name }}</option>@endforeach</select>
                    <span>ضریب مشتری: {{ $p->customer?->rate_multiplier ?? '1.0000' }}</span>
                    <input name="rate_multiplier" type="number" step="0.0001" value="{{ $p->rate_multiplier ?? 1 }}" required>
                    <label class="wt-check"><input type="checkbox" name="is_billable_default" value="1" @checked($p->is_billable_default)> Billable</label>
                    <input name="effective_from" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    <button>ذخیره</button>
                </form>
            </td></tr>
        @endforeach
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

<x-worktracker.panel>
    <div class="wt-section-title"><h3 style="margin:0">نرخ استثنا</h3><x-worktracker.help title="Pricing Override"><p>Override یک نرخ نهایی است. اگر برای پروژه+Activity تعریف شود از Override سطح مشتری اولویت بالاتری دارد. ضریب مشتری و پروژه دوباره روی Override ضرب نمی‌شوند.</p></x-worktracker.help></div>
    <form method="post" action="{{ route('worktracker.billing.overrides.store') }}" class="wt-form wt-form-grid">@csrf
        <label>مشتری<select name="customer_id"><option value="">—</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
        <label>پروژه<select name="project_id"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
        <label>فعالیت<select name="activity_type_id">@foreach($activeTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></label>
        <label>نرخ نهایی ساعتی<input name="hourly_rate_minor" type="number" min="0" required></label>
        <label>واحد<input name="currency" value="IRT" required></label>
        <label>از تاریخ<input name="effective_from" type="datetime-local" required></label>
        <label>تا تاریخ<input name="effective_until" type="datetime-local"></label>
        <label>توضیح<input name="note"></label>
        <button>ثبت نرخ استثنا</button>
    </form>

    @if($overrides->isNotEmpty())
        <div style="margin-top:16px">
            @foreach($overrides as $override)
                <div class="wt-form-card" style="margin-bottom:10px">
                    <form method="post" action="{{ route('worktracker.billing.overrides.update',$override) }}" class="wt-form wt-form-grid">@csrf
                        <label>مشتری<select name="customer_id"><option value="">—</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($override->customer_id===$c->id)>{{ $c->name }}</option>@endforeach</select></label>
                        <label>پروژه<select name="project_id"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected($override->project_id===$p->id)>{{ $p->name }}</option>@endforeach</select></label>
                        <label>فعالیت<select name="activity_type_id">@foreach($types as $t)<option value="{{ $t->id }}" @selected($override->activity_type_id===$t->id)>{{ $t->name }}</option>@endforeach</select></label>
                        <label>نرخ<input name="hourly_rate_minor" type="number" min="0" value="{{ $override->hourly_rate_minor }}" required></label>
                        <label>واحد<input name="currency" value="{{ $override->currency }}" required></label>
                        <label>از<input name="effective_from" type="datetime-local" value="{{ $override->effective_from?->format('Y-m-d\TH:i') }}" required></label>
                        <label>تا<input name="effective_until" type="datetime-local" value="{{ $override->effective_until?->format('Y-m-d\TH:i') }}"></label>
                        <label>توضیح<input name="note" value="{{ $override->note }}"></label>
                        <button>ذخیره Override</button>
                    </form>
                    @if(!$override->effective_until || $override->effective_until->isFuture())
                        <form method="post" action="{{ route('worktracker.billing.overrides.expire',$override) }}" class="wt-row" style="margin-top:8px">@csrf
                            <input type="datetime-local" name="effective_until" value="{{ now()->format('Y-m-d\TH:i') }}">
                            <button class="wt-danger">پایان Override</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-worktracker.panel>

<x-worktracker.panel title="تاریخچه نرخ پایه Activity Type">
    <x-worktracker.table>
        <thead><tr><th>فعالیت</th><th>نرخ</th><th>واحد</th><th>Billable</th><th>اعمال از</th></tr></thead>
        <tbody>
        @forelse($activityRateHistory as $row)
            <tr><td>{{ $row->activity_type_name }} <span class="wt-muted">{{ $row->activity_type_code }}</span></td><td>{{ number_format($row->hourly_rate_minor) }}</td><td>{{ $row->currency }}</td><td>{{ $row->is_billable_default ? 'بله' : 'خیر' }}</td><td>{{ $row->effective_from }}</td></tr>
        @empty<tr><td colspan="5">—</td></tr>@endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>
@endsection

@push('scripts')
<script>
document.getElementById('pricing-preview')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const result = document.getElementById('pricing-preview-result');
    const response = await fetch(@json(route('worktracker.billing.preview')), {
        method: 'POST',
        headers: {'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},
        body: new FormData(form)
    });
    if (!response.ok) {
        result.textContent = 'خطا در محاسبه نرخ';
        return;
    }
    const data = await response.json();
    result.innerHTML = `نرخ پایه تاریخی: <b>${Number(data.base_rate_minor).toLocaleString()}</b> × مشتری <b>${data.customer_multiplier}</b> × پروژه <b>${data.project_multiplier}</b><br>نرخ مؤثر: <b>${Number(data.effective_rate_minor).toLocaleString()} ${data.currency}</b><br>مبلغ: <b>${Number(data.amount_minor).toLocaleString()} ${data.currency}</b><br><small>${data.resolution_source}</small>`;
});
</script>
@endpush

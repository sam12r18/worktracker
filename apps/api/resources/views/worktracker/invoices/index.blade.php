@extends('layouts.worktracker')
@section('content')
    <x-worktracker.page-header title="فاکتورهای ماهانه"
                               subtitle="فاکتور بر اساس Effort جمع‌شونده فعالیت‌ها ساخته می‌شود؛ هم‌پوشانی معتبر حذف یا نرمال نمی‌شود."/>
    <div class="wt-nav"><a href="{{ route('worktracker.dashboard') }}">داشبورد</a><a href="{{ route('worktracker.billing') }}">قیمت‌گذاری</a><a
            href="{{ route('worktracker.invoices.index') }}">فاکتورها</a></div>
    <x-worktracker.panel title="ساخت / بازسازی پیش‌نویس">
        <form method="post" action="{{ route('worktracker.invoices.generate') }}" class="wt-form wt-form-grid">@csrf
            <label>مشتری<select name="customer_id" required>@foreach($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach</select></label>
            <label>از تاریخ<input type="date" name="period_start" value="{{ now()->startOfMonth()->toDateString() }}"
                                  required></label>
            <label>تا تاریخ<input type="date" name="period_end" value="{{ now()->endOfMonth()->toDateString() }}"
                                  required></label>
            <button>ساخت پیش‌نویس</button>
        </form>
    </x-worktracker.panel>
    <x-worktracker.panel title="فاکتورها">
        <x-worktracker.table>
            <thead>
            <tr>
                <th>شماره</th>
                <th>مشتری</th>
                <th>دوره</th>
                <th>وضعیت</th>
                <th>مبلغ</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoices as $i)
                <tr>
                    <td>{{ $i->number ?? 'Draft' }}</td>
                    <td>{{ $i->customer?->name }}</td>
                    <td>{{ $i->period_start->format('Y-m-d') }} تا {{ $i->period_end->format('Y-m-d') }}</td>
                    <td><span class="wt-badge">{{ $i->status }}</span></td>
                    <td class="wt-money">{{ number_format($i->total_minor) }} {{ $i->currency }}</td>
                    <td><a class="wt-btn" href="{{ route('worktracker.invoices.show',$i) }}">مشاهده</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">هنوز فاکتوری ساخته نشده.</td>
                </tr>
            @endforelse
            </tbody>
        </x-worktracker.table>{{ $invoices->links() }}</x-worktracker.panel>
@endsection

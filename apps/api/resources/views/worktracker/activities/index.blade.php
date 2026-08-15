@extends('layouts.worktracker',['title'=>'WorkTracker — ویرایش فعالیت‌ها'])
@section('content')
    <x-worktracker.page-header title="فعالیت‌های تاریخی"
                               subtitle="زمان، پروژه، نوع فعالیت و وضعیت صورتحساب را با Audit کامل اصلاح کن.">
        <x-slot:actions>
            <form method="get" class="wt-row"><input type="date" name="date" value="{{ $date }}"><select name="project_id">
                    <option value="">همه پروژه‌ها</option>@foreach($projects as $p)
                        <option value="{{ $p->id }}" @selected(request('project_id')===$p->id)>{{ $p->name }}</option>
                    @endforeach</select>
                <button class="wt-btn-primary">نمایش</button>
            </form>
        </x-slot:actions>
    </x-worktracker.page-header>
    <x-worktracker.panel title="فعالیت‌های {{ $date }}">
        <x-worktracker.table>
            <thead>
            <tr>
                <th>زمان</th>
                <th>پروژه / نوع</th>
                <th>توضیح</th>
                <th>مدت</th>
                <th>وضعیت</th>
                <th>ویرایش</th>
            </tr>
            </thead>
            <tbody>
            @forelse($activities as $a)
                <tr>
                    <td class="wt-ltr">{{ $a->started_at?->timezone($timezone)->format('H:i') }}
                        – {{ $a->ended_at?->timezone($timezone)->format('H:i') }}</td>
                    <td><strong>{{ $a->project?->name ?? 'Unknown' }}</strong>
                        <div class="wt-muted">{{ $a->activityType?->name ?? 'بدون نوع' }}</div>
                    </td>
                    <td class="wt-break">{{ $a->note ?: $a->window_title ?: '—' }}</td>
                    <td>{{ gmdate('H:i:s',$a->duration_seconds) }}</td>
                    <td>@if(isset($billed[$a->id]))
                            <x-worktracker.badge tone="success">فاکتور نهایی</x-worktracker.badge>
                        @elseif($a->is_billable===false)
                            <x-worktracker.badge tone="warning">Non-billable</x-worktracker.badge>
                        @else
                            <x-worktracker.badge tone="primary">قابل اصلاح</x-worktracker.badge>
                        @endif</td>
                    <td>
                        <details @if(old('activity_id')===$a->id) open @endif>
                            <summary>ویرایش</summary>@if(isset($billed[$a->id]))
                                <div class="wt-muted" style="margin-top:8px">این فعالیت Snapshot مالی دارد و مستقیم ویرایش
                                    نمی‌شود.
                                </div>
                            @else
                                <form method="post" action="{{ route('worktracker.activities.update',$a) }}"
                                      class="wt-form wt-form-card" style="margin-top:8px">@csrf<input type="hidden"
                                                                                                      name="activity_id"
                                                                                                      value="{{ $a->id }}"><input
                                        type="hidden" name="timezone" value="{{ $timezone }}"><label>شروع<input class="wt-ltr"
                                                                                                                type="datetime-local"
                                                                                                                name="started_at"
                                                                                                                value="{{ $a->started_at?->timezone($timezone)->format('Y-m-d\\TH:i') }}"
                                                                                                                required></label><label>پایان<input
                                            class="wt-ltr" type="datetime-local" name="ended_at"
                                            value="{{ $a->ended_at?->timezone($timezone)->format('Y-m-d\\TH:i') }}"
                                            required></label><label>پروژه<select name="project_id">
                                            <option value="">Unknown</option>@foreach($projects as $p)
                                                <option
                                                    value="{{ $p->id }}" @selected($a->project_id===$p->id)>{{ $p->name }}</option>
                                            @endforeach</select></label><label>نوع فعالیت<select name="activity_type_id">
                                            <option value="">بدون نوع</option>@foreach($activityTypes as $t)
                                                <option
                                                    value="{{ $t->id }}" @selected($a->activity_type_id===$t->id)>{{ $t->name }}</option>
                                            @endforeach</select></label><label>صورتحساب<select name="is_billable">
                                            <option value="default" @selected($a->is_billable===null)>پیش‌فرض</option>
                                            <option value="yes" @selected($a->is_billable===true)>بله</option>
                                            <option value="no" @selected($a->is_billable===false)>خیر</option>
                                        </select></label><label>توضیح<textarea
                                            name="note">{{ $a->note }}</textarea></label><label>دلیل اصلاح<input name="reason"
                                                                                                                 required
                                                                                                                 maxlength="1000"
                                                                                                                 placeholder="مثلاً اصلاح پروژه اشتباه تشخیص‌داده‌شده"></label>
                                    <button class="wt-btn-primary">ثبت اصلاح</button>
                                </form>
                            @endif</details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-worktracker.empty title="فعالیتی پیدا نشد"/>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </x-worktracker.table>
    </x-worktracker.panel>
@endsection

@extends('layouts.worktracker',['title'=>'WorkTracker — Audit Log'])
@section('content')
    <x-worktracker.page-header title="Audit Log" subtitle="همه اصلاحات مدیریتی Activityها با قبل/بعد و دلیل تغییر ثبت می‌شوند."/>
    <x-worktracker.panel title="تاریخچه تغییرات">
        <x-worktracker.table>
            <thead>
            <tr>
                <th>زمان</th>
                <th>موجودیت</th>
                <th>عملیات</th>
                <th>دلیل</th>
                <th>قبل / بعد</th>
            </tr>
            </thead>
            <tbody>@forelse($logs as $log)
                <tr>
                    <td class="wt-ltr">{{ $log->created_at }}</td>
                    <td>{{ $log->entity_type }}
                        <div class="wt-muted wt-break">{{ $log->entity_id }}</div>
                    </td>
                    <td>
                        <x-worktracker.badge tone="primary">{{ $log->action }}</x-worktracker.badge>
                    </td>
                    <td>{{ $log->reason ?: '—' }}</td>
                    <td>
                        <details>
                            <summary>مشاهده</summary>
                            <div class="wt-grid" style="margin-top:8px">
                                <div>
                                    <div class="wt-muted">قبل</div>
                                    <pre
                                        class="wt-audit-json">{{ json_encode($log->before_json,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>
                                <div>
                                    <div class="wt-muted">بعد</div>
                                    <pre
                                        class="wt-audit-json">{{ json_encode($log->after_json,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <x-worktracker.empty title="هنوز تغییری ثبت نشده"/>
                    </td>
                </tr>
            @endforelse</tbody>
        </x-worktracker.table>
        <div style="margin-top:12px">{{ $logs->links() }}</div>
    </x-worktracker.panel>
@endsection

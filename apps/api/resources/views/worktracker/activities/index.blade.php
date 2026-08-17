@extends('layouts.worktracker', ['title' => 'WorkTracker — ویرایش فعالیت‌ها'])

@section('content')
<x-worktracker.page-header
    title="فعالیت‌های تاریخی"
    subtitle="زمان، پروژه، نوع فعالیت و وضعیت صورتحساب را با Audit کامل اصلاح کن."
>
    <x-slot:actions>
        <form method="get" class="wt-row">
            <input type="date" name="date" value="{{ $date }}">

            <select name="project_id">
                <option value="">همه پروژه‌ها</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected(request('project_id') === $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>

            <select name="per_page" aria-label="تعداد در صفحه">
                @foreach([25, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} در صفحه</option>
                @endforeach
            </select>

            <button class="wt-btn-primary">نمایش</button>
        </form>
    </x-slot:actions>
</x-worktracker.page-header>

<x-worktracker.panel title="فعالیت‌های {{ $date }}">
    <div class="wt-row" style="justify-content:space-between;margin-bottom:10px">
        <div class="wt-muted">
            @if($activities->total())
                نمایش {{ $activities->firstItem() }} تا {{ $activities->lastItem() }} از {{ $activities->total() }} فعالیت
            @else
                فعالیتی برای این فیلتر وجود ندارد.
            @endif
        </div>
        <div class="wt-muted">صفحه {{ $activities->currentPage() }} از {{ $activities->lastPage() }}</div>
    </div>

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
        @forelse($activities as $activity)
            <tr>
                <td class="wt-ltr">
                    {{ $activity->started_at?->timezone($timezone)->format('H:i') }} –
                    {{ $activity->ended_at?->timezone($timezone)->format('H:i') }}
                </td>
                <td>
                    <strong>{{ $activity->project?->name ?? 'Unknown' }}</strong>
                    <div class="wt-muted">{{ $activity->activityType?->name ?? 'بدون نوع' }}</div>
                </td>
                <td class="wt-break">{{ $activity->note ?: $activity->window_title ?: '—' }}</td>
                <td>{{ gmdate('H:i:s', $activity->duration_seconds) }}</td>
                <td>
                    @if(isset($billed[$activity->id]))
                        <x-worktracker.badge tone="success">فاکتور نهایی</x-worktracker.badge>
                    @elseif($activity->is_billable === false)
                        <x-worktracker.badge tone="warning">Non-billable</x-worktracker.badge>
                    @else
                        <x-worktracker.badge tone="primary">قابل اصلاح</x-worktracker.badge>
                    @endif
                </td>
                <td>
                    @if(isset($billed[$activity->id]))
                        <button type="button" class="wt-btn" disabled title="فعالیت داخل Snapshot مالی است">قفل‌شده</button>
                    @else
                        <button
                            type="button"
                            class="wt-btn"
                            data-wt-activity-edit
                            data-action="{{ route('worktracker.activities.update', $activity) }}"
                            data-id="{{ $activity->id }}"
                            data-project-id="{{ $activity->project_id }}"
                            data-activity-type-id="{{ $activity->activity_type_id }}"
                            data-started-at="{{ $activity->started_at?->timezone($timezone)->format('Y-m-d\TH:i') }}"
                            data-ended-at="{{ $activity->ended_at?->timezone($timezone)->format('Y-m-d\TH:i') }}"
                            data-billable="{{ $activity->is_billable === true ? 'yes' : ($activity->is_billable === false ? 'no' : 'default') }}"
                            data-note="{{ $activity->note }}"
                            data-context="{{ $activity->window_title ?: $activity->process_name ?: 'فعالیت بدون عنوان' }}"
                        >
                            ویرایش
                        </button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6"><x-worktracker.empty title="فعالیتی پیدا نشد" /></td>
            </tr>
        @endforelse
        </tbody>
    </x-worktracker.table>

    @if($activities->hasPages())
        <div style="margin-top:14px">{{ $activities->links() }}</div>
    @endif
</x-worktracker.panel>

<div class="wt-edit-modal" id="wt-activity-edit-modal" hidden aria-hidden="true">
    <div class="wt-edit-modal-backdrop" data-wt-edit-close></div>
    <section class="wt-edit-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wt-activity-edit-title">
        <div class="wt-help-head">
            <div>
                <h3 id="wt-activity-edit-title">ویرایش فعالیت</h3>
                <div class="wt-muted wt-break" id="wt-activity-edit-context"></div>
            </div>
            <button type="button" class="wt-help-close" data-wt-edit-close aria-label="بستن">×</button>
        </div>

        <form method="post" id="wt-activity-edit-form" class="wt-form">
            @csrf
            <input type="hidden" name="activity_id" id="wt-edit-activity-id" value="{{ old('activity_id') }}">
            <input type="hidden" name="timezone" value="{{ $timezone }}">

            <div class="wt-form-grid">
                <label>
                    شروع
                    <input class="wt-ltr" type="datetime-local" name="started_at" id="wt-edit-started-at" required>
                </label>

                <label>
                    پایان
                    <input class="wt-ltr" type="datetime-local" name="ended_at" id="wt-edit-ended-at" required>
                </label>

                <label>
                    پروژه
                    <select name="project_id" id="wt-edit-project-id">
                        <option value="">Unknown</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    نوع فعالیت
                    <select name="activity_type_id" id="wt-edit-activity-type-id">
                        <option value="">بدون نوع</option>
                        @foreach($activityTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="wt-form-grid" style="grid-template-columns:minmax(180px,.5fr) minmax(0,1.5fr)">
                <label>
                    صورتحساب
                    <select name="is_billable" id="wt-edit-billable">
                        <option value="default">پیش‌فرض</option>
                        <option value="yes">بله</option>
                        <option value="no">خیر</option>
                    </select>
                </label>

                <label>
                    دلیل اصلاح
                    <input
                        name="reason"
                        id="wt-edit-reason"
                        required
                        maxlength="1000"
                        placeholder="مثلاً اصلاح پروژه اشتباه تشخیص‌داده‌شده"
                    >
                </label>
            </div>

            <label>
                توضیح
                <textarea name="note" id="wt-edit-note"></textarea>
            </label>

            <div class="wt-actions" style="justify-content:flex-end">
                <button type="button" class="wt-btn" data-wt-edit-close>انصراف</button>
                <button class="wt-btn-primary">ثبت اصلاح</button>
            </div>
        </form>
    </section>
</div>
@endsection

@push('head')
<style>
.wt-edit-modal[hidden]{display:none}.wt-edit-modal{position:fixed;inset:0;z-index:110;display:grid;place-items:center;padding:18px}.wt-edit-modal-backdrop{position:absolute;inset:0;background:rgba(3,7,18,.76);backdrop-filter:blur(6px)}.wt-edit-modal-dialog{position:relative;width:min(820px,100%);max-height:min(88vh,860px);overflow:auto;background:linear-gradient(180deg,#172033,#101827);border:1px solid #334667;border-radius:18px;box-shadow:0 28px 80px rgba(0,0,0,.62);padding:18px}.wt-edit-modal-dialog textarea{min-height:110px}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('wt-activity-edit-modal');
    const form = document.getElementById('wt-activity-edit-form');
    if (!modal || !form) return;

    const fields = {
        id: document.getElementById('wt-edit-activity-id'),
        startedAt: document.getElementById('wt-edit-started-at'),
        endedAt: document.getElementById('wt-edit-ended-at'),
        projectId: document.getElementById('wt-edit-project-id'),
        activityTypeId: document.getElementById('wt-edit-activity-type-id'),
        billable: document.getElementById('wt-edit-billable'),
        note: document.getElementById('wt-edit-note'),
        reason: document.getElementById('wt-edit-reason'),
        context: document.getElementById('wt-activity-edit-context'),
    };

    let lastTrigger = null;

    const open = (trigger, oldData = null) => {
        lastTrigger = trigger;
        form.action = trigger.dataset.action;
        fields.id.value = trigger.dataset.id || '';
        fields.startedAt.value = oldData?.started_at ?? trigger.dataset.startedAt ?? '';
        fields.endedAt.value = oldData?.ended_at ?? trigger.dataset.endedAt ?? '';
        fields.projectId.value = oldData?.project_id ?? trigger.dataset.projectId ?? '';
        fields.activityTypeId.value = oldData?.activity_type_id ?? trigger.dataset.activityTypeId ?? '';
        fields.billable.value = oldData?.is_billable ?? trigger.dataset.billable ?? 'default';
        fields.note.value = oldData?.note ?? trigger.dataset.note ?? '';
        fields.reason.value = oldData?.reason ?? '';
        fields.context.textContent = trigger.dataset.context || '';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        fields.projectId.focus();
    };

    const close = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        lastTrigger?.focus?.();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-wt-activity-edit]');
        if (trigger) {
            open(trigger);
            return;
        }
        if (event.target.closest('[data-wt-edit-close]')) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) close();
    });

    const failedActivityId = {{ Illuminate\Support\Js::from(old('activity_id')) }};
    const oldData = {{ Illuminate\Support\Js::from([
        'started_at' => old('started_at'),
        'ended_at' => old('ended_at'),
        'project_id' => old('project_id'),
        'activity_type_id' => old('activity_type_id'),
        'is_billable' => old('is_billable'),
        'note' => old('note'),
        'reason' => old('reason'),
    ]) }};

    if (failedActivityId) {
        const trigger = document.querySelector(`[data-wt-activity-edit][data-id="${CSS.escape(String(failedActivityId))}"]`);
        if (trigger) open(trigger, oldData);
    }
})();
</script>
@endpush

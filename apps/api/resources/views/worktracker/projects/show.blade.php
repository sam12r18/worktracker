@extends('layouts.worktracker', ['title' => 'WorkTracker — '.$project->name])

@section('content')
@php
    $fmt = fn($s) => sprintf('%02d:%02d', intdiv((int)$s, 3600), intdiv(((int)$s % 3600), 60));
    $statusLabels = ['active'=>'فعال','paused'=>'متوقف','completed'=>'تکمیل','archived'=>'آرشیو'];
    $taskStatuses = ['backlog'=>'Backlog','planned'=>'برنامه‌ریزی','in_progress'=>'در حال انجام','blocked'=>'مسدود','done'=>'انجام‌شده','cancelled'=>'لغو'];
    $priorities = ['low'=>'کم','normal'=>'عادی','high'=>'زیاد','urgent'=>'فوری'];
@endphp

<x-worktracker.page-header :title="$project->name" :subtitle="'کد: '.($project->code ?: '—').' · نسخه Sync: '.$project->version">
    <x-slot:actions>
        <a class="wt-btn" href="{{ route('worktracker.projects.index') }}">همه پروژه‌ها</a>
        @if($project->customer)<a class="wt-btn" href="{{ route('worktracker.customers.show',$project->customer) }}">{{ $project->customer->name }}</a>@endif
    </x-slot:actions>
</x-worktracker.page-header>

<div class="wt-cards">
    <x-worktracker.metric label="Effort ثبت‌شده" :value="$fmt($activityStats->effort_seconds ?? 0)" hint="جمع مستقل Activityها"/>
    <x-worktracker.metric label="Activity" :value="number_format($activityStats->sessions_count ?? 0)" hint="کل سوابق این پروژه"/>
    <x-worktracker.metric label="Rule" :value="(string)$project->rules->count()" hint="قواعد تشخیص محلی"/>
    <x-worktracker.metric label="Task" :value="(string)$tasks->count()" :hint="$tasks->where('status','done')->count().' انجام‌شده'"/>
</div>

<div class="wt-grid-2">
    <x-worktracker.panel title="تنظیمات پروژه">
        <form method="post" action="{{ route('worktracker.projects.update',$project) }}" class="wt-form">
            @csrf
            <label>نام<input name="name" value="{{ old('name',$project->name) }}" required></label>
            <label>کد<input name="code" value="{{ old('code',$project->code) }}"></label>
            <label>مشتری<select name="customer_id"><option value="">بدون مشتری</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id',$project->customer_id)===$c->id)>{{ $c->name }}{{ $c->is_active ? '' : ' (غیرفعال)' }}</option>@endforeach</select></label>
            <label>پروژه والد<select name="parent_id"><option value="">ریشه</option>@foreach($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id',$project->parent_id)===$p->id)>{{ $p->name }}</option>@endforeach</select></label>
            <label>وضعیت<select name="status">@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(old('status',$project->status)===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>رنگ<input type="color" name="color" value="{{ old('color',$project->color ?: '#4d7fff') }}"></label>
            <label>
                <span class="wt-help-inline-title">ضریب پروژه
                    <x-worktracker.help title="ضریب پروژه و سابقه مالی"><p>تغییر این مقدار فقط از «شروع اعتبار» روی قیمت‌گذاری تاریخی اثر می‌گذارد. WorkTracker یک رکورد History جدید ثبت می‌کند.</p></x-worktracker.help>
                </span>
                <input name="rate_multiplier" type="number" step="0.0001" min="0" max="100" value="{{ old('rate_multiplier',$project->rate_multiplier) }}" required>
            </label>
            <label class="wt-check"><input type="checkbox" name="is_billable_default" value="1" @checked(old('is_billable_default',$project->is_billable_default))> پروژه به‌صورت پیش‌فرض Billable باشد</label>
            <label>شروع اعتبار تغییر مالی<input type="datetime-local" name="effective_from" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <label class="wt-check"><input type="checkbox" name="is_archived" value="1" @checked($project->is_archived)> آرشیو شود</label>
            <button class="wt-btn-primary">ذخیره تنظیمات</button>
        </form>
        @if(!$project->is_archived)
            <form method="post" action="{{ route('worktracker.projects.archive',$project) }}" style="margin-top:10px" onsubmit="return confirm('پروژه آرشیو شود؟ داده‌های زمانی حذف نمی‌شوند.')">@csrf<button class="wt-danger">آرشیو پروژه</button></form>
        @else
            <form method="post" action="{{ route('worktracker.projects.restore',$project) }}" style="margin-top:10px">@csrf<button>فعال‌سازی مجدد</button></form>
        @endif
    </x-worktracker.panel>

    <x-worktracker.panel title="تاریخچه مالکیت و ضریب">
        <x-worktracker.table>
            <thead><tr><th>اعمال از</th><th>مشتری</th><th>ضریب</th><th>Billable</th></tr></thead>
            <tbody>
            @forelse($pricingHistory as $row)
                <tr><td>{{ $row->effective_from }}</td><td>{{ $customers->firstWhere('id',$row->customer_id)?->name ?: '—' }}</td><td>{{ $row->multiplier }}</td><td>{{ $row->is_billable_default ? 'بله' : 'خیر' }}</td></tr>
            @empty<tr><td colspan="4">—</td></tr>@endforelse
            </tbody>
        </x-worktracker.table>
        @if($pricingOverrides->isNotEmpty())
            <h4>Overrideهای این پروژه</h4>
            @foreach($pricingOverrides as $override)
                <div class="wt-form-card" style="margin-bottom:7px"><strong>{{ $override->activityType?->name ?: 'Activity' }}</strong> · {{ number_format($override->hourly_rate_minor) }} {{ $override->currency }}<div class="wt-muted">{{ $override->effective_from }} تا {{ $override->effective_until ?: 'بدون پایان' }}</div></div>
            @endforeach
        @endif
    </x-worktracker.panel>
</div>

<x-worktracker.panel style="margin-top:14px">
    <div class="wt-section-title">
        <h3 style="margin:0">Ruleهای تشخیص پروژه</h3>
        <x-worktracker.help title="Rule تشخیص پروژه">
            <p>Rule باید یک الگوی پایدار از Context کاری باشد، نه عنوان کامل یک فایل یا تب. برای مثال در PhpStorm بهتر است به‌جای <code>Ketabnow2 – README.md</code> از <code>WindowTitle contains Ketabnow2</code> استفاده شود.</p>
            <p>بخش «آزمایش روی فعالیت‌های اخیر» نشان می‌دهد Rule پیشنهادی در ۷ روز اخیر چه عنوان‌هایی را Match می‌کند و آیا با پروژه‌های دیگر تداخل دارد.</p>
        </x-worktracker.help>
    </div>

    <form method="post" action="{{ route('worktracker.projects.rules.store',$project) }}" class="wt-form wt-form-grid" id="rule-builder" style="margin-bottom:14px">
        @csrf
        <label>
            نوع
            <select name="rule_type" id="rule-type">
                <option value="ProcessName">Process name</option>
                <option value="WindowTitle" selected>Window title</option>
                <option value="ExecutablePath">Executable path</option>
                <option value="Path">Path</option>
                <option value="Keyword">Keyword</option>
            </select>
        </label>
        <label>
            عملگر
            <select name="operator" id="rule-operator">
                <option value="contains" selected>contains</option>
                <option value="equals">equals</option>
                <option value="starts_with">starts_with</option>
                <option value="ends_with">ends_with</option>
                <option value="regex">regex</option>
            </select>
        </label>
        <label>
            Pattern
            <input name="pattern" id="rule-pattern" required class="wt-ltr" placeholder="مثال: Ketabnow2">
        </label>
        <label>
            نمونه عنوان پنجره
            <input id="rule-sample-title" class="wt-ltr" placeholder="Ketabnow2 – README.md">
        </label>
        <label>Weight<input name="weight" type="number" value="80" min="1" max="200" required></label>
        <label>Priority<input name="priority" type="number" value="0" min="-100000" max="100000" required></label>
        <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" checked> فعال</label>
        <div class="wt-row" style="gap:8px;align-items:end">
            <button type="button" id="suggest-rule-pattern">پیشنهاد الگوی پایدار</button>
            <button>افزودن Rule</button>
        </div>
    </form>

    <div class="wt-form-card" style="margin-bottom:14px">
        <div class="wt-row" style="justify-content:space-between;gap:12px;align-items:center">
            <div>
                <strong>آزمایش Rule روی فعالیت‌های اخیر</strong>
                <div class="wt-muted">حداکثر ۱۰۰ Context اخیر در ۷ روز گذشته بررسی می‌شود؛ این Preview فقط برای تشخیص تداخل است و چیزی را تغییر نمی‌دهد.</div>
            </div>
            <span id="rule-preview-summary" class="wt-badge">Pattern را وارد کن</span>
        </div>
        <div id="rule-preview-details" class="wt-muted" style="margin-top:9px"></div>
        <div id="rule-preview-samples" class="wt-ltr" style="margin-top:9px;font-size:12px;max-height:150px;overflow:auto"></div>
    </div>

    <x-worktracker.table>
        <thead><tr><th>نوع</th><th>عملگر</th><th>Pattern</th><th>Weight</th><th>Priority</th><th>فعال</th><th></th></tr></thead>
        <tbody>
        @forelse($project->rules as $rule)
            <tr>
                <td colspan="7">
                    <form method="post" action="{{ route('worktracker.projects.rules.update',[$project,$rule]) }}" class="wt-inline-form">
                        @csrf
                        <select name="rule_type">@foreach(['ProcessName','WindowTitle','ExecutablePath','Path','Keyword'] as $type)<option value="{{ $type }}" @selected($rule->rule_type===$type)>{{ $type }}</option>@endforeach</select>
                        <select name="operator">@foreach(['contains','equals','starts_with','ends_with','regex'] as $op)<option value="{{ $op }}" @selected($rule->operator===$op)>{{ $op }}</option>@endforeach</select>
                        <input name="pattern" value="{{ $rule->pattern }}" class="wt-field-grow wt-ltr" required>
                        <input name="weight" type="number" value="{{ $rule->weight }}" style="width:90px" required>
                        <input name="priority" type="number" value="{{ $rule->priority }}" style="width:90px" required>
                        <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)> فعال</label>
                        <button>ذخیره</button>
                    </form>
                    <form method="post" action="{{ route('worktracker.projects.rules.destroy',[$project,$rule]) }}" style="margin-top:6px" onsubmit="return confirm('Rule حذف شود؟')">
                        @csrf
                        @method('DELETE')
                        <button class="wt-danger">حذف</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-worktracker.empty title="Rule تعریف نشده"/></td></tr>
        @endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

<script>
(() => {
    const samples = {{ Illuminate\Support\Js::from($recentRuleSamplesForJs) }};
    const currentProjectId = @json((string) $project->id);
    const projectHints = {{ Illuminate\Support\Js::from($projectHintsForJs) }};
    const type = document.getElementById('rule-type');
    const operator = document.getElementById('rule-operator');
    const pattern = document.getElementById('rule-pattern');
    const sampleTitle = document.getElementById('rule-sample-title');
    const summary = document.getElementById('rule-preview-summary');
    const details = document.getElementById('rule-preview-details');
    const sampleOutput = document.getElementById('rule-preview-samples');
    const suggest = document.getElementById('suggest-rule-pattern');

    const normalizeSample = (value) => {
        let title = (value || '').trim();
        const suffixes = [' - Google Chrome', ' - Microsoft Edge', ' — Mozilla Firefox', ' – PhpStorm', ' - PhpStorm', ' - Visual Studio Code'];
        for (const suffix of suffixes) {
            if (title.toLowerCase().endsWith(suffix.toLowerCase())) {
                title = title.slice(0, -suffix.length).trim();
            }
        }

        // If the selected Project name/code is visible in the title, it is a safer browser/IDE
        // pattern than a complete tab/file title. This avoids one Rule per browser tab.
        const projectHint = projectHints
            .filter(hint => String(hint || '').trim().length >= 3)
            .sort((a, b) => String(b).length - String(a).length)
            .find(hint => title.toLocaleLowerCase().includes(String(hint).toLocaleLowerCase()));
        if (projectHint) return String(projectHint).trim();

        for (const separator of [' — ', ' – ', ' - ']) {
            if (title.includes(separator)) {
                const first = title.split(separator)[0].trim();
                if (first.length >= 2) return first;
            }
        }
        return title;
    };

    const valueForSample = (row) => {
        switch (type.value) {
            case 'ProcessName': return row.process || '';
            case 'WindowTitle': return row.title || '';
            case 'ExecutablePath':
            case 'Path': return row.path || '';
            case 'Keyword': return `${row.title || ''} ${row.path || ''}`;
            default: return row.title || '';
        }
    };

    const matches = (value, op, needle) => {
        if (!value || !needle) return false;
        const haystack = value.toLocaleLowerCase();
        const lowered = needle.toLocaleLowerCase();
        if (op === 'equals') return haystack === lowered;
        if (op === 'starts_with') return haystack.startsWith(lowered);
        if (op === 'ends_with') return haystack.endsWith(lowered);
        if (op === 'regex') {
            try { return new RegExp(needle, 'i').test(value); } catch (_) { return false; }
        }
        return haystack.includes(lowered);
    };

    const refreshPreview = () => {
        const needle = pattern.value.trim();
        if (!needle) {
            summary.textContent = 'Pattern را وارد کن';
            details.textContent = '';
            sampleOutput.textContent = '';
            return;
        }
        const matched = samples.filter(row => matches(valueForSample(row), operator.value, needle));
        const same = matched.filter(row => String(row.project_id || '') === currentProjectId).length;
        const other = matched.filter(row => row.project_id && String(row.project_id) !== currentProjectId).length;
        const unknown = matched.filter(row => !row.project_id).length;
        summary.textContent = `${matched.length} Match`;
        details.textContent = `همین پروژه: ${same} · پروژه دیگر: ${other} · بدون پروژه: ${unknown}`;
        summary.classList.toggle('wt-danger', other > 0);
        sampleOutput.innerHTML = matched.slice(0, 12).map(row => `<div>${escapeHtml(row.process || '—')} · ${escapeHtml(row.title || '—')}</div>`).join('');
    };

    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));

    suggest.addEventListener('click', () => {
        const candidate = normalizeSample(sampleTitle.value);
        if (!candidate) return;
        type.value = 'WindowTitle';
        operator.value = 'contains';
        pattern.value = candidate;
        refreshPreview();
    });

    [type, operator, pattern].forEach(el => el.addEventListener('input', refreshPreview));
    refreshPreview();
})();
</script>

<x-worktracker.panel>
    <div class="wt-section-title"><h3 style="margin:0">Taskهای پروژه</h3><x-worktracker.help title="Task و زمان"><p>Task برای برنامه‌ریزی کار است. وضعیت Task مستقل از تایمر است؛ پایان تایمر Task را Done نمی‌کند و Done کردن Task زمان مصنوعی ایجاد نمی‌کند.</p></x-worktracker.help></div>
    <form method="post" action="{{ route('worktracker.projects.tasks.store',$project) }}" class="wt-form wt-form-grid" style="margin-bottom:14px">
        @csrf
        <label>عنوان<input name="title" required></label>
        <label>Task والد<select name="parent_id"><option value="">بدون والد</option>@foreach($tasks as $task)<option value="{{ $task->id }}">{{ $task->title }}</option>@endforeach</select></label>
        <label>وضعیت<select name="status">@foreach($taskStatuses as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <label>اولویت<select name="priority">@foreach($priorities as $value=>$label)<option value="{{ $value }}" @selected($value==='normal')>{{ $label }}</option>@endforeach</select></label>
        <label>Deadline<input type="datetime-local" name="due_at"></label>
        <label>برآورد دقیقه<input type="number" name="estimated_minutes" min="0"></label>
        <label>ترتیب<input type="number" name="sort_order" value="0"></label>
        <label style="grid-column:1/-1">توضیحات<textarea name="description"></textarea></label>
        <button>افزودن Task</button>
    </form>

    @forelse($tasks as $task)
        <form method="post" action="{{ route('worktracker.projects.tasks.update',[$project,$task]) }}" class="wt-form-card" style="margin-bottom:9px">@csrf
            <div class="wt-row" style="justify-content:space-between"><strong>{{ $task->title }}</strong><span class="wt-muted">{{ $task->parent?->title ? 'زیرمجموعه: '.$task->parent->title : '' }}</span></div>
            <div class="wt-form wt-form-grid" style="margin-top:9px">
                <label>عنوان<input name="title" value="{{ $task->title }}" required></label>
                <label>والد<select name="parent_id"><option value="">بدون والد</option>@foreach($tasks->where('id','!=',$task->id) as $parent)<option value="{{ $parent->id }}" @selected($task->parent_id===$parent->id)>{{ $parent->title }}</option>@endforeach</select></label>
                <label>وضعیت<select name="status">@foreach($taskStatuses as $value=>$label)<option value="{{ $value }}" @selected($task->status===$value)>{{ $label }}</option>@endforeach</select></label>
                <label>اولویت<select name="priority">@foreach($priorities as $value=>$label)<option value="{{ $value }}" @selected($task->priority===$value)>{{ $label }}</option>@endforeach</select></label>
                <label>Deadline<input type="datetime-local" name="due_at" value="{{ $task->due_at?->format('Y-m-d\TH:i') }}"></label>
                <label>برآورد دقیقه<input type="number" name="estimated_minutes" min="0" value="{{ $task->estimated_minutes }}"></label>
                <label>ترتیب<input type="number" name="sort_order" value="{{ $task->sort_order }}"></label>
                <label style="grid-column:1/-1">توضیحات<textarea name="description">{{ $task->description }}</textarea></label>
                <button>ذخیره Task</button>
            </div>
        </form>
        <form method="post" action="{{ route('worktracker.projects.tasks.destroy',[$project,$task]) }}" style="margin:-4px 12px 12px" onsubmit="return confirm('Task حذف شود؟')">@csrf @method('DELETE')<button class="wt-danger">حذف Task</button></form>
    @empty
        <x-worktracker.empty title="هنوز Task ثبت نشده" text="Taskها را می‌توان بدون تغییر در منطق Time Tracking مدیریت کرد."/>
    @endforelse
</x-worktracker.panel>
@endsection

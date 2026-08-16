@extends('layouts.worktracker', ['title' => 'WorkTracker — هوشمندی نوع فعالیت'])

@section('content')
@php
    $sourceLabels = [
        'ide_signal' => 'سیگنال IDE',
        'rule' => 'Rule',
        'project_default' => 'پیش‌فرض پروژه',
        'user_override' => 'اصلاح کاربر',
    ];
@endphp

<x-worktracker.page-header title="هوشمندی نوع فعالیت" subtitle="تشخیص Development، Debugging، Testing و سایر نوع‌های کاری به‌صورت قابل توضیح و قابل تنظیم.">
    <x-slot:actions><a class="wt-btn" href="{{ route('worktracker.projects.index') }}">پروژه‌ها</a><a class="wt-btn" href="{{ route('worktracker.billing') }}">Activity Typeها</a></x-slot:actions>
</x-worktracker.page-header>

<div class="wt-cards">
    <x-worktracker.metric label="Activity هفت روز" :value="number_format((int)($stats->sessions_count ?? 0))" hint="مبنای آمار تشخیص"/>
    <x-worktracker.metric label="بدون نوع" :value="number_format((int)($stats->untyped_count ?? 0))" hint="نیازمند Rule یا پیش‌فرض"/>
    <x-worktracker.metric label="با Rule" :value="number_format((int)($stats->rule_count ?? 0))" hint="تشخیص قابل تنظیم"/>
    <x-worktracker.metric label="سیگنال IDE" :value="number_format((int)($stats->ide_signal_count ?? 0))" hint="Debug/Test صریح"/>
</div>

<div class="wt-grid-2">
    <x-worktracker.panel title="Rule جدید نوع فعالیت">
        <form method="post" action="{{ route('worktracker.activity-intelligence.rules.store') }}" class="wt-form">
            @csrf
            <label>
                <span class="wt-help-inline-title">محدوده پروژه
                    <x-worktracker.help title="محدوده Rule"><p>اگر پروژه انتخاب شود Rule فقط وقتی همان پروژه تشخیص داده شده اعمال می‌شود. «همه پروژه‌ها» برای Ruleهای عمومی مثل ProcessName=phpstorm64 مناسب است.</p></x-worktracker.help>
                </span>
                <select name="project_id"><option value="">همه پروژه‌ها</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select>
            </label>
            <label>نوع فعالیت<select name="activity_type_id" required>@foreach($activityTypes as $type)<option value="{{ $type->id }}">{{ $type->name }} ({{ $type->code }})</option>@endforeach</select></label>
            <label>سیگنال<select name="rule_type"><option value="ProcessName">Process name</option><option value="WindowTitle">Window title</option><option value="ExecutablePath">Executable path</option><option value="ContextKey">Context key</option><option value="Keyword">Keyword</option></select></label>
            <label>عملگر<select name="operator"><option value="contains">contains</option><option value="equals">equals</option><option value="starts_with">starts_with</option><option value="ends_with">ends_with</option><option value="regex">regex</option></select></label>
            <label>Pattern<input name="pattern" required class="wt-ltr" placeholder="مثال: phpstorm64"></label>
            <div class="wt-grid-2">
                <label>Weight<input type="number" name="weight" value="80" min="1" max="200" required></label>
                <label>Priority<input type="number" name="priority" value="0" required></label>
            </div>
            <label>
                <span class="wt-help-inline-title">Confidence
                    <x-worktracker.help title="Confidence نوع فعالیت"><p>میزان اطمینان Rule از 0.5 تا 1 است. Rule مبهم را با Confidence پایین‌تر تعریف کن. اصلاح دستی کاربر همیشه 1.0 است.</p></x-worktracker.help>
                </span>
                <input type="number" name="confidence" value="0.90" step="0.01" min="0.5" max="1" required>
            </label>
            <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" checked> فعال</label>
            <button class="wt-btn-primary">افزودن Rule</button>
        </form>
    </x-worktracker.panel>

    <x-worktracker.panel title="ترتیب تصمیم‌گیری">
        <div class="wt-form-card"><strong>1. سیگنال صریح IDE</strong><div class="wt-muted">Debug/Debugger و Test/PHPUnit در IDE با Confidence بالا اولویت دارند.</div></div>
        <div class="wt-form-card" style="margin-top:8px"><strong>2. Ruleهای مدیریتی</strong><div class="wt-muted">Ruleهای پروژه‌ای و عمومی با Priority/Weight رتبه‌بندی می‌شوند.</div></div>
        <div class="wt-form-card" style="margin-top:8px"><strong>3. پیش‌فرض پروژه</strong><div class="wt-muted">اگر هیچ سیگنال قوی وجود نداشته باشد، نوع پیش‌فرض پروژه استفاده می‌شود. برای پروژه‌های توسعه‌ای می‌توان Development را پیش‌فرض گذاشت.</div></div>
        <div class="wt-form-card" style="margin-top:8px"><strong>4. Unknown</strong><div class="wt-muted">اگر هیچ‌کدام معتبر نباشد سیستم نوع فعالیت را حدس نمی‌زند.</div></div>
    </x-worktracker.panel>
</div>

<x-worktracker.panel title="Ruleهای نوع فعالیت" style="margin-top:14px">
    <x-worktracker.table>
        <thead><tr><th>محدوده</th><th>نوع فعالیت</th><th>Rule</th><th>Weight/Priority</th><th>Confidence</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
        @forelse($rules as $rule)
            <tr><td colspan="7">
                <form method="post" action="{{ route('worktracker.activity-intelligence.rules.update',$rule) }}" class="wt-inline-form">@csrf
                    <select name="project_id"><option value="">همه پروژه‌ها</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($rule->project_id===$project->id)>{{ $project->name }}</option>@endforeach</select>
                    <select name="activity_type_id">@foreach($activityTypes as $type)<option value="{{ $type->id }}" @selected($rule->activity_type_id===$type->id)>{{ $type->name }}</option>@endforeach</select>
                    <select name="rule_type">@foreach(['ProcessName','WindowTitle','ExecutablePath','ContextKey','Keyword'] as $value)<option value="{{ $value }}" @selected($rule->rule_type===$value)>{{ $value }}</option>@endforeach</select>
                    <select name="operator">@foreach(['contains','equals','starts_with','ends_with','regex'] as $value)<option value="{{ $value }}" @selected($rule->operator===$value)>{{ $value }}</option>@endforeach</select>
                    <input class="wt-field-grow wt-ltr" name="pattern" value="{{ $rule->pattern }}" required>
                    <input type="number" name="weight" value="{{ $rule->weight }}" style="width:75px" min="1" max="200" required>
                    <input type="number" name="priority" value="{{ $rule->priority }}" style="width:75px" required>
                    <input type="number" name="confidence" value="{{ number_format((float)$rule->confidence,2,'.','') }}" step="0.01" min="0.5" max="1" style="width:82px" required>
                    <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)> فعال</label>
                    <button>ذخیره</button>
                </form>
                <form method="post" action="{{ route('worktracker.activity-intelligence.rules.destroy',$rule) }}" style="margin-top:6px" onsubmit="return confirm('Rule نوع فعالیت غیرفعال شود؟')">@csrf @method('DELETE')<button class="wt-danger">غیرفعال</button></form>
            </td></tr>
        @empty
            <tr><td colspan="7"><x-worktracker.empty title="Rule نوع فعالیت تعریف نشده" text="برای پروژه‌های توسعه‌ای ابتدا Development را به‌عنوان پیش‌فرض پروژه تنظیم کن؛ Rule عمومی را فقط وقتی بساز که الگوی مشترک و قابل اعتماد داری. Debug/Test صریح اولویت بالاتری دارند."/></td></tr>
        @endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

<x-worktracker.panel title="آخرین تشخیص‌ها" style="margin-top:14px">
    <x-worktracker.table>
        <thead><tr><th>زمان</th><th>پروژه</th><th>نوع</th><th>منبع</th><th>Confidence</th><th>دلیل</th><th>عنوان</th></tr></thead>
        <tbody>
        @forelse($recent as $row)
            <tr>
                <td class="wt-ltr">{{ $row->started_at?->timezone(config('worktracker.display_timezone','Asia/Tehran'))->format('m/d H:i') }}</td>
                <td>{{ $row->project?->name ?? 'Unknown' }}</td>
                <td>{{ $row->activityType?->name ?? '—' }}</td>
                <td>{{ $sourceLabels[$row->activity_type_source] ?? ($row->activity_type_source ?: 'قدیمی/نامشخص') }}</td>
                <td>{{ $row->activity_type_confidence === null ? '—' : number_format((float)$row->activity_type_confidence,2) }}</td>
                <td class="wt-break">{{ $row->activity_type_reason ?: '—' }}</td>
                <td class="wt-break">{{ $row->window_title ?: $row->process_name ?: '—' }}</td>
            </tr>
        @empty<tr><td colspan="7"><x-worktracker.empty title="هنوز تشخیص جدیدی ثبت نشده"/></td></tr>@endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>
@endsection

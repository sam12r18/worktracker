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
    <div class="wt-section-title"><h3 style="margin:0">Ruleهای تشخیص پروژه</h3><x-worktracker.help title="Rule تشخیص پروژه"><p>Agent هنگام بستن یک foreground session، Ruleهای فعال را وزن‌دهی می‌کند. Rule قوی‌تر/اولویت بالاتر احتمال انتخاب این پروژه را افزایش می‌دهد.</p><p>برای الگوهای Regex فقط وقتی لازم است استفاده کن؛ Ruleهای contains/equals قابل پیش‌بینی‌تر هستند.</p></x-worktracker.help></div>
    <form method="post" action="{{ route('worktracker.projects.rules.store',$project) }}" class="wt-form wt-form-grid" style="margin-bottom:14px">
        @csrf
        <label>نوع<select name="rule_type"><option value="ProcessName">Process name</option><option value="WindowTitle">Window title</option><option value="ExecutablePath">Executable path</option><option value="Path">Path</option><option value="Keyword">Keyword</option></select></label>
        <label>عملگر<select name="operator"><option value="contains">contains</option><option value="equals">equals</option><option value="starts_with">starts_with</option><option value="ends_with">ends_with</option><option value="regex">regex</option></select></label>
        <label>Pattern<input name="pattern" required class="wt-ltr"></label>
        <label>Weight<input name="weight" type="number" value="50" min="1" max="200" required></label>
        <label>Priority<input name="priority" type="number" value="0" min="-100000" max="100000" required></label>
        <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" checked> فعال</label>
        <button>افزودن Rule</button>
    </form>

    <x-worktracker.table>
        <thead><tr><th>نوع</th><th>عملگر</th><th>Pattern</th><th>Weight</th><th>Priority</th><th>فعال</th><th></th></tr></thead>
        <tbody>
        @forelse($project->rules as $rule)
            <tr>
                <td colspan="7">
                    <form method="post" action="{{ route('worktracker.projects.rules.update',[$project,$rule]) }}" class="wt-inline-form">@csrf
                        <select name="rule_type">@foreach(['ProcessName','WindowTitle','ExecutablePath','Path','Keyword'] as $type)<option value="{{ $type }}" @selected($rule->rule_type===$type)>{{ $type }}</option>@endforeach</select>
                        <select name="operator">@foreach(['contains','equals','starts_with','ends_with','regex'] as $op)<option value="{{ $op }}" @selected($rule->operator===$op)>{{ $op }}</option>@endforeach</select>
                        <input name="pattern" value="{{ $rule->pattern }}" class="wt-field-grow wt-ltr" required>
                        <input name="weight" type="number" value="{{ $rule->weight }}" style="width:90px" required>
                        <input name="priority" type="number" value="{{ $rule->priority }}" style="width:90px" required>
                        <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)> فعال</label>
                        <button>ذخیره</button>
                    </form>
                    <form method="post" action="{{ route('worktracker.projects.rules.destroy',[$project,$rule]) }}" style="margin-top:6px" onsubmit="return confirm('Rule حذف شود؟')">@csrf @method('DELETE')<button class="wt-danger">حذف</button></form>
                </td>
            </tr>
        @empty<tr><td colspan="7"><x-worktracker.empty title="Rule تعریف نشده"/></td></tr>@endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

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

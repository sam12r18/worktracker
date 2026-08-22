@extends('layouts.worktracker', ['title' => 'WorkTracker — Context مرورگر'])

@section('content')
<x-worktracker.page-header title="Context مرورگر" subtitle="Chrome Context Bridge · Host / Path / Title با حریم خصوصی محدودشده">
    <x-slot:actions>
        <a class="wt-btn" href="{{ route('worktracker.projects.index') }}">پروژه‌ها</a>
        <a class="wt-btn" href="{{ route('worktracker.diagnostics.index') }}">لاگ سیستم</a>
    </x-slot:actions>
</x-worktracker.page-header>

<div class="wt-cards">
    <x-worktracker.metric label="Context اخیر" :value="number_format($contexts->count())" hint="حداکثر ۱۰۰ Activity اخیر"/>
    <x-worktracker.metric label="Host مشاهده‌شده" :value="number_format($hostCount)" hint="در نمونه Contextهای اخیر"/>
    <x-worktracker.metric label="Browser Rule" :value="number_format($browserRules->count())" hint="Host / Path / Title"/>
    <x-worktracker.metric label="آخرین دریافت" :value="$lastContext?->started_at?->diffForHumans() ?: '—'" hint="پس از Sync Agent"/>
</div>

<div class="wt-grid-2">
    <x-worktracker.panel title="ساخت Browser Rule">
        <div class="wt-help-note" style="margin-bottom:12px">
            افزونه Chrome فقط Context را می‌فرستد؛ زمان، Idle و مرز Activity همچنان توسط Windows Agent تعیین می‌شود. QueryString و Fragment ذخیره نمی‌شوند و Incognito در P0 نادیده گرفته می‌شود.
        </div>

        <form method="post" action="{{ route('worktracker.browser-context.rules.store') }}" class="wt-form" id="browser-rule-form">
            @csrf
            <label>پروژه
                <select name="project_id" id="browser-project" required>
                    <option value="">انتخاب پروژه</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id')===$project->id)>{{ $project->name }}{{ $project->code ? ' · '.$project->code : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label>نوع Rule
                <select name="rule_type" id="browser-rule-type" required>
                    @foreach(['BrowserHost'=>'Host','BrowserPath'=>'Path','BrowserTitle'=>'Title'] as $value=>$label)
                        <option value="{{ $value }}" @selected(old('rule_type','BrowserPath')===$value)>{{ $value }} · {{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>عملگر
                <select name="operator" id="browser-operator" required>
                    @foreach(['contains','equals','starts_with','ends_with','regex'] as $operator)
                        <option value="{{ $operator }}" @selected(old('operator','contains')===$operator)>{{ $operator }}</option>
                    @endforeach
                </select>
            </label>
            <label>Pattern
                <input name="pattern" id="browser-pattern" class="wt-ltr" value="{{ old('pattern') }}" placeholder="/sam12r18/worktracker یا github.com" required>
            </label>
            <div class="wt-form-grid">
                <label>Weight<input name="weight" type="number" min="1" max="200" value="{{ old('weight',100) }}" required></label>
                <label>Priority<input name="priority" type="number" min="-100000" max="100000" value="{{ old('priority',10) }}" required></label>
                <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled',true))> فعال</label>
            </div>
            <button class="wt-btn-primary">ثبت Browser Rule</button>
        </form>
    </x-worktracker.panel>

    <x-worktracker.panel title="Ruleهای مرورگر">
        @forelse($browserRules as $rule)
            <div class="wt-form-card" style="margin-bottom:9px">
                <div class="wt-row" style="justify-content:space-between;margin-bottom:8px">
                    <div><strong>{{ $rule->project?->name ?: '—' }}</strong> · <span class="wt-badge">نسخه {{ $rule->version }}</span></div>
                    <span class="{{ $rule->is_enabled ? 'wt-ok' : 'wt-muted' }}">{{ $rule->is_enabled ? 'فعال' : 'غیرفعال' }}</span>
                </div>

                <form method="post" action="{{ route('worktracker.projects.rules.update',[$rule->project_id,$rule]) }}" class="wt-form">
                    @csrf
                    <div class="wt-form-grid">
                        <label>نوع
                            <select name="rule_type" required>
                                @foreach(['BrowserHost'=>'Host','BrowserPath'=>'Path','BrowserTitle'=>'Title'] as $value=>$label)
                                    <option value="{{ $value }}" @selected($rule->rule_type===$value)>{{ $value }} · {{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>عملگر
                            <select name="operator" required>
                                @foreach(['contains','equals','starts_with','ends_with','regex'] as $operator)
                                    <option value="{{ $operator }}" @selected($rule->operator===$operator)>{{ $operator }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label>Pattern<input name="pattern" value="{{ $rule->pattern }}" class="wt-ltr" required></label>
                    <div class="wt-form-grid">
                        <label>Weight<input name="weight" type="number" min="1" max="200" value="{{ $rule->weight }}" required></label>
                        <label>Priority<input name="priority" type="number" min="-100000" max="100000" value="{{ $rule->priority }}" required></label>
                        <label class="wt-check"><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)> فعال</label>
                    </div>
                    <button>ذخیره Browser Rule</button>
                </form>

                <form method="post" action="{{ route('worktracker.projects.rules.destroy',[$rule->project_id,$rule]) }}" style="margin-top:8px" onsubmit="return confirm('Browser Rule حذف شود؟')">
                    @csrf
                    @method('DELETE')
                    <button class="wt-danger">حذف</button>
                </form>
            </div>
        @empty
            <x-worktracker.empty title="Browser Rule تعریف نشده" text="از Contextهای اخیر برای ساخت Rule دقیق Host یا Path استفاده کن."/>
        @endforelse
    </x-worktracker.panel>
</div>

<x-worktracker.panel title="Contextهای اخیر Chrome">
    <div class="wt-muted" style="margin-bottom:10px">دکمه‌های Host/Path/Title فقط فرم بالا را پر می‌کنند و تا زمان ثبت هیچ Ruleای ساخته نمی‌شود.</div>
    <x-worktracker.table>
        <thead><tr><th>زمان</th><th>پروژه</th><th>Host</th><th>Path</th><th>Title</th><th>ساخت Rule</th></tr></thead>
        <tbody>
        @forelse($contexts as $session)
            @php
                $browser = is_array($session->browser_context) ? $session->browser_context : [];
                $host = (string)($browser['host'] ?? '');
                $path = (string)($browser['path'] ?? '');
                $title = (string)($browser['title'] ?? $session->window_title ?? '');
            @endphp
            <tr>
                <td class="wt-money">{{ $session->started_at?->format('Y-m-d H:i:s') }}</td>
                <td>{{ $session->project?->name ?: 'تشخیص‌داده‌نشده' }}</td>
                <td class="wt-ltr wt-break">{{ $host ?: '—' }}</td>
                <td class="wt-ltr wt-break">{{ $path ?: '—' }}</td>
                <td class="wt-break" style="max-width:320px">{{ $title ?: '—' }}</td>
                <td>
                    <div class="wt-actions">
                        @if($host)<button type="button" class="wt-browser-fill" data-type="BrowserHost" data-pattern="{{ $host }}" data-project="{{ $session->project_id }}">Host</button>@endif
                        @if($path)<button type="button" class="wt-browser-fill" data-type="BrowserPath" data-pattern="{{ $path }}" data-project="{{ $session->project_id }}">Path</button>@endif
                        @if($title)<button type="button" class="wt-browser-fill" data-type="BrowserTitle" data-pattern="{{ $title }}" data-project="{{ $session->project_id }}">Title</button>@endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-worktracker.empty title="هنوز Browser Context دریافت نشده" text="Extension و Native Host را فعال کن؛ پس از ثبت Activity و Sync، Context اینجا نمایش داده می‌شود."/></td></tr>
        @endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>

<script>
(() => {
    const form = document.getElementById('browser-rule-form');
    const project = document.getElementById('browser-project');
    const type = document.getElementById('browser-rule-type');
    const pattern = document.getElementById('browser-pattern');
    if (!form || !project || !type || !pattern) return;

    document.querySelectorAll('.wt-browser-fill').forEach(button => {
        button.addEventListener('click', () => {
            type.value = button.dataset.type || 'BrowserPath';
            pattern.value = button.dataset.pattern || '';
            if (button.dataset.project && [...project.options].some(option => option.value === button.dataset.project)) {
                project.value = button.dataset.project;
            }
            pattern.focus();
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>
@endsection

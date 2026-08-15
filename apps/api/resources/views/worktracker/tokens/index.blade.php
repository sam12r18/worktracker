@extends('layouts.worktracker', ['title' => 'WorkTracker — API و Token'])

@section('content')
<x-worktracker.page-header title="API و Access Token" subtitle="توکن‌های Sanctum برای اتصال Windows Agent یا ابزارهای مدیریتی API.">
    <x-slot:help>
        <p><strong>Device Token</strong> فقط برای یک Windows Agent ساخته می‌شود و به UUID همان دستگاه Bind است.</p>
        <ol>
            <li>در Agent وارد تب «همگام‌سازی» شو.</li>
            <li>«کپی شناسه کامل دستگاه» را بزن.</li>
            <li>UUID را در فرم Device Token وارد کن.</li>
            <li>Token ساخته‌شده را همان لحظه کپی و در فیلد Sanctum Token Agent قرار بده.</li>
            <li>آدرس API در Agent باید ریشه سایت باشد؛ مثال لوکال: <code>http://127.0.0.1:8082</code>.</li>
        </ol>
        <p><strong>Admin API Token</strong> برای Agent معمولی نیست و قابلیت‌های <code>admin:read</code> و <code>admin:write</code> دارد.</p>
    </x-slot:help>
</x-worktracker.page-header>

<div class="wt-grid-2" style="margin-top:16px">
    <x-worktracker.panel title="ساخت Token جدید">
        @if(session('new_worktracker_token'))
            <div class="wt-help-note wt-ltr wt-break" style="margin-bottom:14px">
                <strong>این مقدار فقط همین یک‌بار نمایش داده می‌شود:</strong><br>
                <code style="user-select:all">{{ session('new_worktracker_token') }}</code>
            </div>
        @endif

        <form method="post" action="{{ route('worktracker.tokens.store') }}" class="wt-form" id="wt-token-form">
            @csrf
            <label>
                <span class="wt-help-inline-title">نوع Token
                    <x-worktracker.help title="نوع Token">
                        <p><strong>Windows Device:</strong> کمترین سطح دسترسی لازم برای register و sync یک دستگاه.</p>
                        <p><strong>Admin API:</strong> برای اسکریپت/Integration مدیریتی؛ به Agent نده.</p>
                    </x-worktracker.help>
                </span>
                <select name="kind" id="wt-token-kind" required>
                    <option value="device">Windows Device</option>
                    <option value="admin">Admin API</option>
                </select>
            </label>

            <label>نام Token<input name="label" required maxlength="80" placeholder="مثلاً لپ‌تاپ دفتر"></label>

            <label id="wt-device-id-row">
                <span class="wt-help-inline-title">Device UUID
                    <x-worktracker.help title="Device UUID از کجا بیاید؟">
                        <p>این UUID را خود Windows Agent تولید و در SQLite محلی نگه می‌دارد.</p>
                        <p>در تب «همگام‌سازی» Agent روی «کپی شناسه کامل دستگاه» بزن و مقدار را اینجا Paste کن. حتی اگر دستگاه هنوز در سرور Register نشده باشد، همین UUID برای ساخت Token معتبر است.</p>
                    </x-worktracker.help>
                </span>
                <input class="wt-ltr" name="device_id" id="wt-device-id" list="wt-known-devices" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                <datalist id="wt-known-devices">
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}">{{ $device->name }} {{ $device->operator_label ? '— '.$device->operator_label : '' }}</option>
                    @endforeach
                </datalist>
            </label>

            <label>
                <span class="wt-help-inline-title">انقضا (روز)
                    <x-worktracker.help title="انقضای Token">
                        <p>برای Device بهتر است Token دائمی نسازیم. پیش‌فرض سیستم از تنظیمات WorkTracker می‌آید؛ مقدار خالی یعنی همان پیش‌فرض.</p>
                    </x-worktracker.help>
                </span>
                <input type="number" name="expires_in_days" min="1" max="365" placeholder="پیش‌فرض سیستم">
            </label>

            <button class="wt-btn-primary">ساخت Token</button>
        </form>
    </x-worktracker.panel>

    <x-worktracker.panel title="چرخه اتصال Windows Agent">
        <div class="wt-stack">
            <div class="wt-form-card"><strong>۱. Device UUID</strong><div class="wt-muted">Agent شناسه محلی خودش را تولید می‌کند.</div></div>
            <div class="wt-form-card"><strong>۲. ساخت Device Token</strong><div class="wt-muted">Abilityها: device:register + device:sync + device:&lt;UUID&gt;</div></div>
            <div class="wt-form-card"><strong>۳. ذخیره در Windows</strong><div class="wt-muted">Token با DPAPI برای همان حساب Windows رمزگذاری می‌شود.</div></div>
            <div class="wt-form-card"><strong>۴. Register و Sync</strong><div class="wt-muted">Agent با Bearer Token به <code>/api/v1/devices</code> و <code>/api/v1/sync</code> وصل می‌شود.</div></div>
        </div>
    </x-worktracker.panel>
</div>

<x-worktracker.panel title="Tokenهای فعال" style="margin-top:14px">
    <x-worktracker.table>
        <thead><tr><th>نام</th><th>Abilityها</th><th>آخرین استفاده</th><th>انقضا</th><th></th></tr></thead>
        <tbody>
        @forelse($tokens as $token)
            <tr>
                <td><strong>{{ $token->name }}</strong></td>
                <td class="wt-break wt-ltr">{{ implode(', ', $token->abilities ?? []) }}</td>
                <td>{{ $token->last_used_at ?: '—' }}</td>
                <td>{{ $token->expires_at ?: '—' }}</td>
                <td>
                    <form method="post" action="{{ route('worktracker.tokens.destroy', $token->id) }}" onsubmit="return confirm('این Token فوراً غیرقابل استفاده شود؟')">
                        @csrf @method('DELETE')
                        <button class="wt-danger">Revoke</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-worktracker.empty title="هنوز Token ساخته نشده"/></td></tr>
        @endforelse
        </tbody>
    </x-worktracker.table>
</x-worktracker.panel>
@endsection

@push('scripts')
<script>
(() => {
    const kind = document.getElementById('wt-token-kind');
    const row = document.getElementById('wt-device-id-row');
    const input = document.getElementById('wt-device-id');
    const update = () => {
        const device = kind?.value === 'device';
        if (row) row.style.display = device ? '' : 'none';
        if (input) input.required = device;
    };
    kind?.addEventListener('change', update);
    update();
})();
</script>
@endpush

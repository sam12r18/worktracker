<nav class="wt-nav-shell" aria-label="منوی WorkTracker">
    <a class="wt-brand" href="{{ route('worktracker.dashboard') }}">
        <span class="wt-brand-mark">W</span>
        <span>
            <strong>WorkTracker</strong>
            <small>مدیریت زمان، پروژه و صورتحساب</small>
        </span>
    </a>

    @php
        $links = [
            ['worktracker.dashboard', 'worktracker.dashboard', 'داشبورد'],
            ['worktracker.projects.*', 'worktracker.projects.index', 'پروژه‌ها'],
            ['worktracker.customers.*', 'worktracker.customers.index', 'مشتری‌ها'],
            ['worktracker.activities.*', 'worktracker.activities.index', 'فعالیت‌ها'],
            ['worktracker.reports.*', 'worktracker.reports.index', 'گزارش‌ها'],
            ['worktracker.billing*', 'worktracker.billing', 'قیمت‌گذاری'],
            ['worktracker.invoices.*', 'worktracker.invoices.index', 'فاکتورها'],
            ['worktracker.tokens.*', 'worktracker.tokens.index', 'API و Token'],
            ['worktracker.conflicts*', 'worktracker.conflicts', 'تعارض‌ها'],
            ['worktracker.audit.*', 'worktracker.audit.index', 'Audit'],
        ];
    @endphp

    <div class="wt-nav-links">
        @foreach($links as [$pattern, $route, $label])
            <a class="{{ request()->routeIs($pattern) ? 'is-active' : '' }}" href="{{ route($route) }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>

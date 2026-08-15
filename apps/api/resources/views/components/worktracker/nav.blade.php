<nav class="wt-nav-shell" aria-label="منوی WorkTracker">
    <a class="wt-brand" href="{{ route('worktracker.dashboard') }}"><span
            class="wt-brand-mark">W</span><span><strong>WorkTracker</strong><small>مدیریت زمان، پروژه و صورتحساب</small></span></a>
    <div class="wt-nav-links">
        @foreach([['worktracker.dashboard','داشبورد'],['worktracker.activities.index','فعالیت‌ها'],['worktracker.reports.index','گزارش‌ها'],['worktracker.billing','قیمت‌گذاری'],['worktracker.invoices.index','فاکتورها'],['worktracker.conflicts','تعارض‌ها'],['worktracker.audit.index','Audit']] as [$route,$label])
            <a class="{{ request()->routeIs($route) ? 'is-active':'' }}" href="{{ route($route) }}">{{ $label }}</a>
        @endforeach
    </div>
</nav>

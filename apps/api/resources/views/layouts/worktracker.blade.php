<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><title>{{ $title ?? 'WorkTracker' }}</title>
<style>
:root{font-family:Vazirmatn,Tahoma,Arial,sans-serif;color-scheme:dark;color:#e6edf7;background:#0b1020;--bg:#0b1020;--bg-elevated:#0f1728;--panel:#121a2b;--panel-2:#172033;--panel-3:#0d1424;--border:#2a3854;--border-soft:#202c42;--text:#e6edf7;--muted:#92a0b5;--primary:#6ea8fe;--primary-strong:#4d7fff;--primary-soft:#172a4f;--danger:#ff7b72;--danger-soft:#3a1f27;--success:#56d364;--success-soft:#173326;--warn:#e3b341;--warn-soft:#3b3017;--radius:18px;--shadow:0 16px 44px rgba(0,0,0,.24)}*{box-sizing:border-box}html{background:var(--bg)}body{margin:0;background:radial-gradient(circle at 85% -10%,rgba(78,127,255,.14),transparent 32%),linear-gradient(180deg,#0a0f1d 0,#0d1424 300px,#0b1020 100%);min-height:100vh;color:var(--text)}a{color:inherit}::selection{background:#315ca8;color:#fff}::-webkit-scrollbar{width:11px;height:11px}::-webkit-scrollbar-track{background:#0d1424}::-webkit-scrollbar-thumb{background:#35435f;border:3px solid #0d1424;border-radius:999px}::-webkit-scrollbar-thumb:hover{background:#465879}.wt-app{width:min(1320px,100%);margin:auto;padding:18px}.wt-nav-shell{position:sticky;top:10px;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:16px;background:rgba(18,26,43,.92);backdrop-filter:blur(16px);border:1px solid var(--border);box-shadow:var(--shadow);padding:10px 12px;border-radius:18px;margin-bottom:18px}.wt-brand{display:flex;gap:10px;align-items:center;text-decoration:none;white-space:nowrap}.wt-brand-mark{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:linear-gradient(145deg,var(--primary-strong),#7a8cff);color:#fff;font-weight:900;box-shadow:0 8px 22px rgba(78,127,255,.28)}.wt-brand small{display:block;color:var(--muted);font-size:10px;margin-top:2px}.wt-nav-links{display:flex;gap:4px;overflow-x:auto}.wt-nav-links a{padding:8px 11px;border-radius:10px;text-decoration:none;color:#aeb9ca;font-size:13px;white-space:nowrap}.wt-nav-links a:hover,.wt-nav-links a.is-active{background:var(--primary-soft);color:#a9c7ff;font-weight:700}.wt-top,.wt-row,.wt-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.wt-top{justify-content:space-between}.wt-page-head{display:flex;justify-content:space-between;align-items:end;gap:16px;margin:8px 2px 18px}.wt-page-head h1{margin:0;font-size:24px;color:#f4f7fb}.wt-page-head p{margin:6px 0 0;color:var(--muted);font-size:13px}.wt-cards,.wt-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.wt-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.65fr);gap:14px}.wt-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.wt-card,.wt-panel{background:linear-gradient(180deg,rgba(23,32,51,.78),rgba(18,26,43,.98));border:1px solid var(--border);border-radius:var(--radius);padding:16px;min-width:0;box-shadow:var(--shadow)}.wt-panel{margin-bottom:14px}.wt-panel h2,.wt-panel h3{margin-top:0;color:#f0f4fa}.wt-metric{font-size:25px;font-weight:800;margin-top:8px;font-variant-numeric:tabular-nums;color:#f5f8fc}.wt-muted{color:var(--muted);font-size:12px}.wt-flash,.wt-alert{background:var(--success-soft);border:1px solid #2d6847;color:#b7f0c9;padding:10px 12px;border-radius:12px;margin:10px 0}.wt-danger{color:var(--danger)!important}.wt-ok{color:var(--success)}.wt-warn{color:var(--warn)}.wt-table-wrap{width:100%;overflow:auto;-webkit-overflow-scrolling:touch;border-radius:12px;border:1px solid var(--border-soft)}.wt-table{width:100%;border-collapse:separate;border-spacing:0;min-width:620px}.wt-table th,.wt-table td{text-align:right;padding:11px 10px;border-bottom:1px solid var(--border-soft);font-size:13px;vertical-align:middle}.wt-table th{color:#aeb9ca;font-weight:700;background:#111a2c;position:sticky;top:0}.wt-table tr:last-child td{border-bottom:0}.wt-table tbody tr{transition:background .15s ease}.wt-table tbody tr:hover{background:#17233a}input,select,button,.wt-btn,textarea{border:1px solid #31405f;border-radius:10px;padding:8px 10px;background:#0d1424;color:var(--text);text-decoration:none;min-height:38px;max-width:100%;font:inherit;outline:none;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}input::placeholder,textarea::placeholder{color:#687790}input:hover,select:hover,textarea:hover{border-color:#435577}input:focus,select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(110,168,254,.13);background:#10192b}select{color-scheme:dark}textarea{min-height:72px}button,.wt-btn{cursor:pointer;display:inline-flex;align-items:center;justify-content:center;background:#172033;border-color:#34435f;color:#dbe4f2}button:hover,.wt-btn:hover{border-color:#58719a;background:#1c2941}.wt-btn-primary{background:linear-gradient(145deg,var(--primary-strong),#5b8fff);border-color:#5d8ff7;color:#fff;box-shadow:0 7px 18px rgba(78,127,255,.18)}.wt-btn-primary:hover{background:linear-gradient(145deg,#5d89ff,#6ea8fe);border-color:#79afff}.wt-field-grow{flex:1 1 180px;min-width:0}.wt-ltr{direction:ltr;text-align:left}.wt-break{overflow-wrap:anywhere;word-break:break-word}.wt-stack>*+*{margin-top:10px}.wt-form{display:grid;gap:10px}.wt-form label{display:grid;gap:5px;font-size:12px;color:var(--muted)}.wt-form-grid{grid-template-columns:repeat(4,minmax(0,1fr));align-items:end}.wt-inline-form{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.wt-check{display:flex!important;grid-auto-flow:column;justify-content:start;align-items:center;gap:6px!important}.wt-money{font-variant-numeric:tabular-nums;white-space:nowrap}.wt-badge{display:inline-flex;padding:4px 8px;border-radius:999px;background:#202b40;color:#bdc7d7;font-size:11px;font-weight:700;border:1px solid #2d3a54}.wt-badge-success{background:var(--success-soft);color:#82e6a1;border-color:#295f43}.wt-badge-danger{background:var(--danger-soft);color:#ffaaa4;border-color:#6c3440}.wt-badge-warning{background:var(--warn-soft);color:#f1cf70;border-color:#685624}.wt-badge-primary{background:var(--primary-soft);color:#a9c7ff;border-color:#2e4f87}.wt-empty{text-align:center;color:var(--muted);padding:28px 16px;border:1px dashed #34435f;border-radius:14px;background:#0e1627}.wt-empty strong{display:block;color:#c6d0df;margin-bottom:5px}.wt-kpi-bar{height:7px;background:#222e43;border-radius:99px;overflow:hidden}.wt-kpi-bar>i{display:block;height:100%;background:linear-gradient(90deg,var(--primary-strong),#7ca8ff);border-radius:99px}.wt-timeline{position:relative;min-width:900px;padding:12px 0}.wt-timeline-axis{display:grid;grid-template-columns:repeat(12,1fr);color:var(--muted);font-size:10px;border-bottom:1px solid var(--border);padding-bottom:7px}.wt-timeline-row{position:relative;height:44px;border-bottom:1px solid var(--border-soft)}.wt-timeline-label{position:absolute;right:0;top:9px;width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px}.wt-timeline-track{position:absolute;right:160px;left:0;top:7px;height:30px;background:#0d1424;border:1px solid #1d2a41;border-radius:8px}.wt-timeline-bar{position:absolute;top:3px;height:22px;min-width:3px;border-radius:7px;background:linear-gradient(90deg,#4d7fff,#7aa7ff);color:#fff;font-size:9px;padding:4px 6px;overflow:hidden;white-space:nowrap;box-shadow:0 3px 12px rgba(78,127,255,.28)}.wt-app nav[role="navigation"]{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}.wt-app nav[role="navigation"] svg{width:18px;height:18px}.wt-audit-json{direction:ltr;text-align:left;white-space:pre-wrap;background:#0b1220;border:1px solid var(--border-soft);padding:9px;border-radius:10px;font-size:11px;max-height:180px;overflow:auto;color:#bdc8d8}.wt-form-card{background:#0f1728;border:1px solid var(--border-soft);border-radius:14px;padding:12px}.wt-summary>div{background:#10192b;border-radius:12px;padding:12px;border:1px solid var(--border-soft)}.wt-pricing-result{margin-top:12px;padding:12px;border-radius:10px;background:#0d1424;border:1px solid var(--border-soft);line-height:2}details>summary{cursor:pointer;color:#c7d2e3}code{color:#b7ceff}hr{border:0;border-top:1px solid var(--border)}.wt-help-trigger{width:24px;height:24px;min-height:24px;padding:0;border-radius:999px;background:#1d3158;border:1px solid #466aa6;color:#b9d2ff;font-size:13px;font-weight:900;line-height:1;vertical-align:middle;box-shadow:none}.wt-help-trigger:hover{background:#254271;border-color:#6ea8fe;color:#fff}.wt-help-floating{position:fixed;left:20px;bottom:20px;z-index:70;width:44px;height:44px;min-height:44px;font-size:20px;background:linear-gradient(145deg,#4d7fff,#6ea8fe);border-color:#8bbaff;color:#fff;box-shadow:0 12px 30px rgba(36,79,160,.4)}.wt-help-modal[hidden]{display:none}.wt-help-modal{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:18px}.wt-help-backdrop{position:absolute;inset:0;background:rgba(3,7,18,.72);backdrop-filter:blur(5px)}.wt-help-dialog{position:relative;width:min(620px,100%);max-height:min(78vh,760px);overflow:auto;background:linear-gradient(180deg,#172033,#101827);border:1px solid #334667;border-radius:18px;box-shadow:0 28px 80px rgba(0,0,0,.55);padding:18px}.wt-help-head{display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid var(--border-soft);padding-bottom:12px;margin-bottom:12px}.wt-help-head h3{margin:0;color:#f4f7fb}.wt-help-close{width:34px;height:34px;min-height:34px;padding:0;font-size:18px}.wt-help-body{color:#c8d2e1;line-height:1.9;font-size:13px}.wt-help-body p{margin:0 0 10px}.wt-help-body ul,.wt-help-body ol{margin:8px 0;padding-right:20px}.wt-help-inline-title{display:inline-flex;align-items:center;gap:6px}.wt-help-note{padding:10px 12px;border:1px solid #30486f;background:#12213c;border-radius:12px;color:#c7d9f8}.wt-subnav{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 14px}.wt-subnav a{padding:7px 10px;border-radius:9px;text-decoration:none;color:#afbdd2;border:1px solid var(--border-soft);background:#101827}.wt-subnav a:hover,.wt-subnav a.is-active{color:#bcd3ff;border-color:#43669d;background:#172a4f}.wt-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wt-section-title{display:flex;align-items:center;gap:7px;margin:0 0 12px}.wt-color-dot{display:inline-block;width:12px;height:12px;border-radius:4px;border:1px solid rgba(255,255,255,.28)}
@media(max-width:980px){.wt-grid{grid-template-columns:1fr}.wt-grid-2{grid-template-columns:1fr}.wt-grid-3{grid-template-columns:1fr}.wt-cards,.wt-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.wt-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wt-nav-shell{align-items:flex-start;flex-direction:column}.wt-nav-links{width:100%}}
@media(max-width:600px){.wt-app{padding:8px}.wt-nav-shell{position:static;border-radius:14px;margin-bottom:12px}.wt-brand small{display:none}.wt-page-head{align-items:stretch;flex-direction:column}.wt-page-head h1{font-size:20px}.wt-cards,.wt-summary{grid-template-columns:1fr 1fr;gap:8px}.wt-card,.wt-panel{padding:12px;border-radius:14px}.wt-metric{font-size:20px}.wt-form-grid{grid-template-columns:1fr}.wt-inline-form>*{width:100%}.wt-table th,.wt-table td{padding:9px 7px;font-size:12px}.wt-actions>*{flex:1 1 auto}}
</style>
@stack('head')
</head>
<body>
<div class="wt-app">
    <x-worktracker.nav/>

    @if(session('status'))
        <div class="wt-flash">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="wt-alert" style="background:var(--danger-soft);border-color:#6c3440;color:#ffb2ad">
            <strong>برخی اطلاعات نیاز به اصلاح دارند:</strong>
            <ul style="margin:8px 0 0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</div>

<x-worktracker.context-help/>

<div class="wt-help-modal" id="wt-help-modal" hidden aria-hidden="true">
    <div class="wt-help-backdrop" data-wt-help-close></div>
    <section class="wt-help-dialog" role="dialog" aria-modal="true" aria-labelledby="wt-help-title">
        <div class="wt-help-head">
            <h3 id="wt-help-title">راهنما</h3>
            <button type="button" class="wt-help-close" data-wt-help-close aria-label="بستن">×</button>
        </div>
        <div class="wt-help-body" id="wt-help-body"></div>
    </section>
</div>

<script>
(() => {
    const modal = document.getElementById('wt-help-modal');
    const title = document.getElementById('wt-help-title');
    const body = document.getElementById('wt-help-body');
    let lastTrigger = null;

    const close = () => {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        lastTrigger?.focus?.();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-wt-help-target]');
        if (trigger && modal && title && body) {
            const template = document.getElementById(trigger.dataset.wtHelpTarget);
            const content = template?.content?.firstElementChild;
            if (!content) return;
            lastTrigger = trigger;
            title.textContent = content.dataset.title || 'راهنما';
            body.innerHTML = content.innerHTML;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector('.wt-help-close')?.focus();
            return;
        }
        if (event.target.closest('[data-wt-help-close]')) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) close();
    });
})();
</script>
@stack('scripts')
</body>
</html>

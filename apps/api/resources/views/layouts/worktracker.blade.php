<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? 'WorkTracker' }}</title>
    <style>
        :root {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            color: #182235;
            background: #f5f7fb;
            --bg: #f5f7fb;
            --panel: #fff;
            --border: #e4e9f2;
            --muted: #6e7a8d;
            --primary: #3158d4;
            --primary-soft: #edf1ff;
            --danger: #b42318;
            --success: #177245;
            --warn: #9a6700;
            --radius: 18px;
            --shadow: 0 8px 30px rgba(27, 39, 75, .055)
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: linear-gradient(180deg, #f8faff 0, #f4f6fa 260px);
            min-height: 100vh
        }

        a {
            color: inherit
        }

        .wt-app {
            width: min(1320px, 100%);
            margin: auto;
            padding: 18px
        }

        .wt-nav-shell {
            position: sticky;
            top: 10px;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: rgba(255, 255, 255, .94);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 10px 12px;
            border-radius: 18px;
            margin-bottom: 18px
        }

        .wt-brand {
            display: flex;
            gap: 10px;
            align-items: center;
            text-decoration: none;
            white-space: nowrap
        }

        .wt-brand-mark {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-weight: 900
        }

        .wt-brand small {
            display: block;
            color: var(--muted);
            font-size: 10px;
            margin-top: 2px
        }

        .wt-nav-links {
            display: flex;
            gap: 4px;
            overflow-x: auto
        }

        .wt-nav-links a {
            padding: 8px 11px;
            border-radius: 10px;
            text-decoration: none;
            color: #536074;
            font-size: 13px;
            white-space: nowrap
        }

        .wt-nav-links a:hover, .wt-nav-links a.is-active {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700
        }

        .wt-top, .wt-row, .wt-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap
        }

        .wt-top {
            justify-content: space-between
        }

        .wt-page-head {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 16px;
            margin: 8px 2px 18px
        }

        .wt-page-head h1 {
            margin: 0;
            font-size: 24px
        }

        .wt-page-head p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px
        }

        .wt-cards, .wt-summary {
            display: grid;
            grid-template-columns:repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 16px 0
        }

        .wt-grid {
            display: grid;
            grid-template-columns:minmax(0, 1.55fr) minmax(300px, .65fr);
            gap: 14px
        }

        .wt-grid-3 {
            display: grid;
            grid-template-columns:repeat(3, minmax(0, 1fr));
            gap: 14px
        }

        .wt-card, .wt-panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            min-width: 0;
            box-shadow: var(--shadow)
        }

        .wt-panel {
            margin-bottom: 14px
        }

        .wt-panel h2, .wt-panel h3 {
            margin-top: 0
        }

        .wt-metric {
            font-size: 25px;
            font-weight: 800;
            margin-top: 8px;
            font-variant-numeric: tabular-nums
        }

        .wt-muted {
            color: var(--muted);
            font-size: 12px
        }

        .wt-flash, .wt-alert {
            background: #ecf8f0;
            border: 1px solid #bee4ca;
            padding: 10px 12px;
            border-radius: 12px;
            margin: 10px 0
        }

        .wt-danger {
            color: var(--danger)
        }

        .wt-ok {
            color: var(--success)
        }

        .wt-warn {
            color: var(--warn)
        }

        .wt-table-wrap {
            width: 100%;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px
        }

        .wt-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 620px
        }

        .wt-table th, .wt-table td {
            text-align: right;
            padding: 11px 10px;
            border-bottom: 1px solid #edf0f5;
            font-size: 13px;
            vertical-align: middle
        }

        .wt-table th {
            color: #637086;
            font-weight: 700;
            background: #fafbfe;
            position: sticky;
            top: 0
        }

        .wt-table tr:last-child td {
            border-bottom: 0
        }

        .wt-table tbody tr:hover {
            background: #fafcff
        }

        input, select, button, .wt-btn, textarea {
            border: 1px solid #d8deea;
            border-radius: 10px;
            padding: 8px 10px;
            background: #fff;
            color: inherit;
            text-decoration: none;
            min-height: 38px;
            max-width: 100%;
            font: inherit
        }

        textarea {
            min-height: 72px
        }

        button, .wt-btn {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center
        }

        button:hover, .wt-btn:hover {
            border-color: #aebadc
        }

        .wt-btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff
        }

        .wt-field-grow {
            flex: 1 1 180px;
            min-width: 0
        }

        .wt-ltr {
            direction: ltr;
            text-align: left
        }

        .wt-break {
            overflow-wrap: anywhere;
            word-break: break-word
        }

        .wt-stack > * + * {
            margin-top: 10px
        }

        .wt-form {
            display: grid;
            gap: 10px
        }

        .wt-form label {
            display: grid;
            gap: 5px;
            font-size: 12px;
            color: var(--muted)
        }

        .wt-form-grid {
            grid-template-columns:repeat(4, minmax(0, 1fr));
            align-items: end
        }

        .wt-inline-form {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap
        }

        .wt-check {
            display: flex !important;
            grid-auto-flow: column;
            justify-content: start;
            align-items: center;
            gap: 6px !important
        }

        .wt-money {
            font-variant-numeric: tabular-nums;
            white-space: nowrap
        }

        .wt-badge {
            display: inline-flex;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef2f8;
            font-size: 11px;
            font-weight: 700
        }

        .wt-badge-success {
            background: #e9f8ef;
            color: #177245
        }

        .wt-badge-danger {
            background: #fdebeb;
            color: #a8241d
        }

        .wt-badge-warning {
            background: #fff5dc;
            color: #8a5a00
        }

        .wt-badge-primary {
            background: var(--primary-soft);
            color: var(--primary)
        }

        .wt-empty {
            text-align: center;
            color: var(--muted);
            padding: 28px 16px;
            border: 1px dashed #d9dfeb;
            border-radius: 14px;
            background: #fbfcfe
        }

        .wt-empty strong {
            display: block;
            color: #455066;
            margin-bottom: 5px
        }

        .wt-kpi-bar {
            height: 7px;
            background: #edf1f7;
            border-radius: 99px;
            overflow: hidden
        }

        .wt-kpi-bar > i {
            display: block;
            height: 100%;
            background: var(--primary);
            border-radius: 99px
        }

        .wt-timeline {
            position: relative;
            min-width: 900px;
            padding: 12px 0
        }

        .wt-timeline-axis {
            display: grid;
            grid-template-columns:repeat(12, 1fr);
            color: var(--muted);
            font-size: 10px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 7px
        }

        .wt-timeline-row {
            position: relative;
            height: 44px;
            border-bottom: 1px solid #f0f2f7
        }

        .wt-timeline-label {
            position: absolute;
            right: 0;
            top: 9px;
            width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px
        }

        .wt-timeline-track {
            position: absolute;
            right: 160px;
            left: 0;
            top: 7px;
            height: 30px;
            background: #fafbfe;
            border-radius: 8px
        }

        .wt-timeline-bar {
            position: absolute;
            top: 4px;
            height: 22px;
            min-width: 3px;
            border-radius: 7px;
            background: linear-gradient(90deg, #3158d4, #5f7ee4);
            color: #fff;
            font-size: 9px;
            padding: 4px 6px;
            overflow: hidden;
            white-space: nowrap;
            box-shadow: 0 2px 7px rgba(49, 88, 212, .18)
        }

        .wt-app nav[role="navigation"] {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap
        }

        .wt-app nav[role="navigation"] svg {
            width: 18px;
            height: 18px
        }

        .wt-audit-json {
            direction: ltr;
            text-align: left;
            white-space: pre-wrap;
            background: #f7f9fc;
            padding: 9px;
            border-radius: 10px;
            font-size: 11px;
            max-height: 180px;
            overflow: auto
        }

        .wt-form-card {
            background: #fbfcff;
            border: 1px solid #edf0f6;
            border-radius: 14px;
            padding: 12px
        }

        .wt-summary > div {
            background: #fafbfe;
            border-radius: 12px;
            padding: 12px;
            border: 1px solid #edf0f6
        }

        .wt-pricing-result {
            margin-top: 12px;
            padding: 12px;
            border-radius: 10px;
            background: #f7f8fb;
            line-height: 2
        }

        @media (max-width: 980px) {
            .wt-grid {
                grid-template-columns:1fr
            }

            .wt-grid-3 {
                grid-template-columns:1fr
            }

            .wt-cards, .wt-summary {
                grid-template-columns:repeat(2, minmax(0, 1fr))
            }

            .wt-form-grid {
                grid-template-columns:repeat(2, minmax(0, 1fr))
            }

            .wt-nav-shell {
                align-items: flex-start;
                flex-direction: column
            }

            .wt-nav-links {
                width: 100%
            }
        }

        @media (max-width: 600px) {
            .wt-app {
                padding: 8px
            }

            .wt-nav-shell {
                position: static;
                border-radius: 14px;
                margin-bottom: 12px
            }

            .wt-brand small {
                display: none
            }

            .wt-page-head {
                align-items: stretch;
                flex-direction: column
            }

            .wt-page-head h1 {
                font-size: 20px
            }

            .wt-cards, .wt-summary {
                grid-template-columns:1fr 1fr;
                gap: 8px
            }

            .wt-card, .wt-panel {
                padding: 12px;
                border-radius: 14px
            }

            .wt-metric {
                font-size: 20px
            }

            .wt-form-grid {
                grid-template-columns:1fr
            }

            .wt-inline-form > * {
                width: 100%
            }

            .wt-table th, .wt-table td {
                padding: 9px 7px;
                font-size: 12px
            }

            .wt-actions > * {
                flex: 1 1 auto
            }
        }
    </style>@stack('head')</head>
<body>
<div class="wt-app">
    <x-worktracker.nav/>@if(session('status'))
        <div class="wt-flash">{{ session('status') }}</div>
    @endif
    @yield('content')</div>
</body>
</html>

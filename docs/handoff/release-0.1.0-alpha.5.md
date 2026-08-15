# WorkTracker 0.1.0-alpha.5 — Billing Foundation

Implemented:
- Customers with multiplicative rate multiplier.
- Activity Types with base hourly rates.
- Project customer assignment and project multiplier.
- Effective pricing formula: base × customer × project.
- Project/activity and customer/activity explicit rate overrides.
- Effective-dated override records.
- Billable/non-billable defaults and per-activity field.
- Billing pricing preview in authenticated WorkTracker dashboard.
- BillingRateSnapshot schema reserved for invoice finalization.
- ADR 0009 and billing architecture documentation.

Not yet included:
- Final invoice lifecycle, invoice numbering, tax, discount, payment status.
- PDF/Excel invoice generation.
- Historical billing snapshot finalization command.
- Windows Agent UI for selecting activity type (planned next; server foundation is ready).

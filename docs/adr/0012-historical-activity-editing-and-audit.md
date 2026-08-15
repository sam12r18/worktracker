# ADR 0012 — Historical Activity Editing and Audit
Status: Accepted — 2026-08-12

Historical activities may be corrected by an authenticated WorkTracker administrator. Every change records before/after JSON, reason, user, IP and user agent. The editor may change project, activity type, billable state, note, start/end and recalculates duration. Activity Effort remains additive and is never normalized against overlapping activity.

Activities already represented by a finalized BillingRateSnapshot are immutable through the historical editor. Financial corrections must use invoice adjustments or a future credit/rebill workflow.

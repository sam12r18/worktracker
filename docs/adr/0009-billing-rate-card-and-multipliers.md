# ADR 0009 — Billing Rate Card and Multiplicative Pricing

Status: Accepted — 2026-08-12

## Decision

WorkTracker pricing is activity-centric. Every activity type may define a base hourly rate.

Effective rate is:

`base activity rate × customer multiplier × project multiplier`

Customer and project multipliers default to `1.0000` and multiply together.

Explicit pricing overrides have higher precedence:
1. Project + activity type explicit hourly rate.
2. Customer + activity type explicit hourly rate.
3. Base activity rate × customer multiplier × project multiplier.

An explicit override is the final hourly rate. Multipliers MUST NOT be applied again.

Rates and overrides are effective-dated. Final invoices will snapshot all pricing inputs so historical invoices never change after later rate edits.

## Time accounting invariant

Billing consumes additive WorkTracker Effort, not elapsed coverage. Concurrent activities remain independently billable when they are legitimate separate work activities. Billing MUST NOT normalize Effort to wall-clock time.

## Follow-up

Effective-dated history for base activity rates and customer/project multipliers is formalized in ADR 0011.

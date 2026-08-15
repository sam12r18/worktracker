# ADR 0011 — Effective-Dated Base Rates and Multipliers

Status: Accepted — 2026-08-12

## Problem
Final invoice snapshots prevent future edits from changing a finalized invoice, but they do not protect a historical Draft created after a rate change. Using only the current Activity Type rate or current customer/project multiplier would price older work with newer commercial terms.

## Decision
WorkTracker keeps append-only effective-dated histories for:
- Activity Type base hourly rate + currency.
- Customer multiplier.
- Project multiplier.

`PricingService` resolves each value using the latest history row where `effective_from <= ActivitySession.started_at`, then applies explicit effective-dated Pricing Overrides.

The current value on ActivityType/Customer/Project is an administrative convenience and Sync/display value; historical billing uses the history tables.

Existing alpha.5 rows are backfilled from an epoch baseline during migration so older Activity Sessions still resolve deterministically.

Billing configuration is server-authoritative. Device Tokens may receive project billing metadata and Activity Types via Pull but may not mutate customer/multiplier/rate configuration through project Sync.

## Full historical billing context
The history rows also carry the configuration that can change invoice attribution: Activity Type billable default, customer currency, and Project customer assignment + billable default. Historical draft generation MUST resolve these values at the Activity timestamp rather than using today's configuration.

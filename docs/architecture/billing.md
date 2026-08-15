# Billing Architecture

## Formula
`Effective hourly rate = Base Activity Rate × Customer Multiplier × Project Multiplier`

Multipliers multiply together and default to `1.0000`.

Explicit effective-dated rate precedence:
1. Project + Activity Type final hourly override.
2. Customer + Activity Type final hourly override.
3. Historical base rate × historical customer multiplier × historical project multiplier.

Explicit overrides are final rates; multipliers are not applied again.

## Historical pricing
Current columns are not enough for retrospective Draft generation. The authoritative historical inputs are:
- `activity_rate_history`
- `customer_multiplier_history`
- `project_multiplier_history`
- `pricing_overrides`

Resolution uses the Activity start timestamp. Final invoice line items and `billing_rate_snapshots` freeze the resolved values again at Finalization.

## Billable semantics
- `ActivitySession.is_billable` can explicitly force true/false.
- A project with `is_billable_default=false` disables billing by default.
- Otherwise the Activity Type `is_billable_default` decides.
- Final manual override on the Activity wins.

## Authority boundary
Laravel is authoritative for customers, rate cards, multipliers, overrides and invoice money.
Windows caches Activity Types and project billing metadata for UX/offline context, but Device Tokens do not author commercial settings.

## Currency
Amounts are integer units (`*_minor` naming is retained for storage consistency). alpha.6 does not perform FX conversion. A generated invoice must contain one currency; mixed-currency line resolution is rejected until an explicit conversion model is added.

## Additive effort
Billing consumes legitimate additive Effort, not Elapsed Coverage. Concurrent Activity Sessions may each generate a full independent line item.

## Historical project ownership
`project_multiplier_history` snapshots the effective customer assignment, project multiplier, and project billable default. Historical drafts therefore use the customer that owned the project at the activity timestamp, not the project's current customer.

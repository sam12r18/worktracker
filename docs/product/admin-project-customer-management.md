# Admin Project / Customer Management — alpha.7.2

## Purpose

The authenticated WorkTracker web dashboard is now the authoritative management surface for project/customer master data instead of relying on the Billing screen or API-only project CRUD.

## P0 — Project management

- create/update/archive/restore projects;
- customer assignment during project creation and later edits;
- optional parent project;
- status, code and color;
- project billing multiplier and billable default;
- effective-dated project/customer ownership + multiplier history;
- deterministic Project Rule CRUD.

Archiving is non-destructive. Historical Activity Sessions remain attached to the project.

## P1 — Customer and pricing operations

- create/update/activate/deactivate customers;
- company name, currency, multiplier and billing notes;
- customer detail page with projects, invoices, pricing overrides and multiplier history;
- Activity Type active state and sort order;
- Activity Type historical rates;
- Pricing Override list/update/expire workflow.

Pricing Overrides are expired rather than casually deleted because removing a historical pricing input can change a retrospective Draft calculation. Final invoices remain protected by immutable snapshots.

## P2 — Task management

Project detail includes Task CRUD using the already-existing MVP Task model:

- parent Task;
- status;
- priority;
- due date;
- estimated minutes;
- sort order;
- description.

Task state remains independent from time tracking. Completing a Task does not invent time and stopping a timer does not complete a Task.

## Sync authority

Laravel remains authoritative for customer/commercial settings. Projects and Project Rules continue to be syncable to Windows Agents. Existing additive time-accounting invariants are unchanged.

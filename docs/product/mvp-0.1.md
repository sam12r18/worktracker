# MVP 0.1 Product Scope

## Purpose

Automatically capture focused work on Windows, allow manual project/time annotation, synchronize to Laravel, and create trustworthy daily project work reports.

## User stories

- I can see what project I am currently working on.
- I can pause and resume automatic tracking.
- I can assign an unknown detected activity to a project.
- I can teach the system deterministic project rules.
- I can manually start a project or task timer.
- I can create, prioritize and change the status of project tasks.
- I can add/edit a historical manual time entry.
- I can review idle intervals and reclassify them when appropriate.
- I can use the same account on multiple Windows PCs.
- Each PC keeps its own activity records.
- Parallel work from different PCs remains parallel time and contributes separately to project person-hours.
- I can see daily totals and generate a daily work report.

## MVP entities

- User
- Device
- Project
- ProjectRule
- Task
- ApplicationIdentity
- ActivitySession
- ManualTimeEntry (represented as ActivitySession with source)
- SyncOutbox (local)
- DailyReport

## Deferred

- Browser extension
- AI report summarization
- Git commit/repository integration
- Team/organization RBAC beyond basic ownership
- Billing/invoicing
- Screenshot monitoring
- Keyboard/mouse content capture
- Mobile agent

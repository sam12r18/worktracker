# WorkTracker 0.1.0-alpha.1.1 — Additive Concurrency Correction

Date: 2026-08-11

This corrective release makes overlapping work a first-class accounting rule.

## Core rule

Legitimate activities are independent records and remain additive even when they overlap on the same user, device, project, and exact time interval.

Example: a 20-minute phone call plus 20 minutes of coding from 10:00–10:20 for the same project produces:

- Effort: 40 minutes
- Elapsed Coverage: 20 minutes
- Concurrent Effort: 20 minutes

## Changes

- multiple manual timers may run concurrently
- manual timers no longer suppress automatic foreground tracking
- Windows dashboard exposes Effort, Elapsed Coverage, and Concurrent Effort separately
- local `TimeAccountingService` uses interval union for Coverage and additive sum for Effort
- Laravel `TimeAccountingService` now implements the same semantics
- activity-session list API returns the accounting summary and explicit overlap policy
- removed obsolete activity write routes; activity writes remain owned by idempotent `/sync`
- updated AGENTS, ADR, architecture, API, test, status, and handoff documentation

## Verification

- all PHP files pass `php -l`
- local SQLite schema executes successfully in a clean SQLite database
- WPF XAML and csproj parse as valid XML
- .NET build/runtime verification is still required on Windows with .NET 10 SDK

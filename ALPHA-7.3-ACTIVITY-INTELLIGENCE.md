# WorkTracker 0.1.0-alpha.7.3 — Activity Intelligence & Work Events

This patch is an overlay for the current alpha.7.2 source tree.

## Scope

- Raw foreground sessions remain immutable/auditable source records.
- WPF derives `WorkEvent` projections to aggregate file/tab/application noise.
- Same-Project foreground switches are rendered as one Work Event and are counted once.
- A short observed interruption can create a bounded additive `ContinuityBridge` for the anchor Project:
  - maximum interruption: 120 seconds;
  - minimum direct anchor before a bridge: 120 seconds;
  - minimum direct anchor after a bridge before another bridge: 120 seconds;
  - idle, pause, sleep, WorkTracker UI and unobserved gaps never bridge.
- Event-level Project correction and Activity Type assignment update all raw sessions in the event.
- `Assign + Learn` uses stable IDE workspace patterns and safe Project name/code hints in browsers.
- The Web Project page includes a Rule Builder suggestion and a recent-activity collision preview.
- Debug/Test Activity Type inference is conservative and requires explicit title signals.

## Important accounting boundary

Continuity Bridge credit is currently a derived local Windows projection. Raw sessions remain the Sync source of truth and Laravel billing/report materialization continues to use raw persisted sessions. Server-side bridge parity must be implemented and audited before derived continuity credit participates in finalized invoices.

## Install

Extract this patch over the repository root, preserving paths, then run:

```powershell
cd I:\worktracker\apps\api
php artisan optimize:clear

cd I:\worktracker
.\tools\build-windows-agent.ps1
```

There is no new Laravel or SQLite migration in this patch.

## Verification

Use `docs/testing/alpha7.3-activity-intelligence-smoke-test.md` after the Windows build succeeds.

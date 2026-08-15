# Release Handoff — WorkTracker 0.1.0-alpha.6

Read first: `AGENTS.md`, `docs/status.md`, `docs/handoff/current-project-map.md`, ADR 0009 and ADR 0010.

Implemented in this release:
- Windows Activity Type offline cache and manual timer selector.
- Optional manual billable override (tri-state: true/false/inherit).
- Activity Sync carries Activity Type and billable flag.
- Server Pull includes Activity Types and project Billing metadata.
- alpha.5 Project fillable bug fixed for customer/multiplier/default billability.
- Conflict payloads updated for Billing fields.
- Monthly invoice draft/rebuild, finalization and immutable price snapshots.
- effective-dated base rate/customer/project multiplier histories for retrospective Draft correctness.
- Timeline Activity Type assignment for auto-captured Activities without changing time.
- Invoice exclusion counters for untyped and non-billable Activities.
- Invoice item boundary clipping and finalized-activity double-billing guard.
- Excel-compatible export and print/save-PDF page.
- Responsive invoice pages using shared Blade primitives.

Known verification boundary:
- No .NET 10 SDK is available in the packaging environment, so WPF requires a real Windows build.
- Laravel integration requires testing inside the actual host app with its auth/Sanctum and MariaDB/MySQL.

## Final hardening
Historical pricing resolution includes the project→customer assignment, project billable default, Activity Type billable default and customer currency at the activity date. Draft invoices therefore do not silently drift when current configuration changes later.

# alpha.7.3 Source ↔ Documentation Audit

Date: 2026-08-16

## Purpose

This audit compares the current WorkTracker source with the development contract, ADRs, architecture notes, roadmap and current status before continuing Activity Intelligence work.

## Aligned and implemented

- Additive Effort remains a non-negotiable invariant; overlapping legitimate activities are not capped to wall-clock Coverage.
- Windows foreground capture remains sequential on a device while manual/other valid sources may overlap.
- Offline-first SQLite + transactional Outbox + device-bound Sanctum Sync remain the source transport model.
- Project/Customer management, Project Rules, Task web foundation, token management, contextual Help and Sync diagnostics match the alpha.7.2 documented milestone.
- Project Rule operators (`contains`, `equals`, `starts_with`, `ends_with`, `regex`) are represented in both Laravel and Windows Agent.
- Raw Activity Sessions remain auditable and synchronized rather than being destructively compacted for presentation.

## Mismatches corrected in alpha.7.3

### Window title was acting as an Activity boundary

Previous Agent capture legitimately created a raw session whenever the foreground title changed, but the UI also treated each raw row as a separate user-visible Activity. In IDEs this produced one visible item per file/tab.

Correction: raw capture remains detailed, while WPF now derives Work Events using Project/context continuity. Window-title churn is no longer automatically a visible work boundary.

### Project learning was too literal

The previous `Assign + Learn` flow could learn a complete title such as `Ketabnow2 – README.md`, which does not scale to repositories with many files.

Correction: IDE learning uses stable workspace patterns. Browser learning prefers a selected Project name/code only when it is present in the observed title; unsafe broad browser-process rules are not generated automatically. The Web Rule Builder previews collisions against recent activity.

### Project classification and Activity Type classification were conflated conceptually

Project detection is often possible from stable title/path/process patterns, but Development vs Debugging/Testing cannot be inferred safely from the IDE process alone.

Correction: alpha.7.3 introduces a separate conservative Activity Type inference layer. Only explicit Debug/Test title signals are inferred. Deep IDE mode integration remains a 0.2 item.

### Short project continuity was not represented

The documented additive model allows genuine parallel credit, but foreground aggregation had no bounded representation for a short interruption while the user immediately resumes the anchor Project.

Correction: ADR 0014 now defines a per-project derived Continuity Bridge with a 120-second maximum interruption, 60-second initial anchor requirement, 120-second per-project re-arm requirement, mutual/multi-project bridge support, and no credit for idle/pause/sleep/WorkTracker UI/unobserved gaps.

## Deliberate open gaps

### P0 before continuity credit affects finalized billing

The Windows Agent currently derives Continuity Bridge credit locally for Work Event/Effort presentation. Laravel reports and invoice materialization still use raw persisted Activity Sessions. Server-side projection/audit parity must be implemented and tested before bridge credit is allowed into finalized invoices.

### Deep browser context

Without an explicit browser extension, the Agent does not have authoritative URL/domain context. Browser title patterns remain a heuristic and must be previewed for collisions.

### Deep IDE activity mode

Without a PhpStorm/VS Code adapter, run/debug/test/review/terminal state cannot be treated as authoritative. Plain IDE foreground remains untyped unless the user assigns an Activity Type.

### Team/RBAC ownership

Organization/team membership and per-project/customer access control remain intentionally deferred until the ownership model is specified. Existing user ownership/isolation must not be weakened by a cosmetic user-management UI.

## Validation gate

- PHP syntax: pass in packaging environment.
- Existing additive time/pricing invariants: pass.
- WPF XAML: XML-valid and event-handler references statically checked.
- Final WPF compiler gate: must be run with `.NET 10` on Windows using `tools/build-windows-agent.ps1`.

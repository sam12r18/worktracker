# AGENTS.md — WorkTracker Development Contract

This file is the mandatory development contract for every coding agent or developer working on this repository.

## 1. Product invariant

WorkTracker measures project activity while preserving the identity of **user + device**.

### Never perform cross-device time deduplication

Two devices may be used concurrently by two different people on the same project. Therefore:

- Do not merge overlapping sessions from different devices.
- Do not reduce project time because two devices overlap.
- Preserve `user_id`, `device_id`, `started_at`, `ended_at`, and source for every activity.
- Reports may expose both raw duration and person-hours, but raw activity must remain immutable/auditable.

The auto-foreground stream on one device must remain sequential because Windows has one foreground window. This is only a capture-integrity constraint. **All legitimate activities from other sources may overlap and remain additive, including on the same user + device + project.**

### Raw sessions vs Work Events (alpha.7.3)

- Raw `activity_sessions` remain auditable source records; do not destructively compact them just to make the UI cleaner.
- WPF may aggregate adjacent raw foreground sessions into a derived Work Event.
- Same-Project switching between applications is one logical Work Event when contiguous and must not create duplicate Project credit.
- A bounded Continuity Bridge may credit the anchor Project across an **observed** interruption of at most 120 seconds only after at least 120 seconds of direct anchor work, and must re-arm with another 120 seconds of direct anchor work before another bridge.
- Idle, pause, sleep and unobserved gaps must never receive automatic continuity credit.
- Continuity Bridge is derived, not a new raw source in alpha.7.3. Server reporting/billing must not silently assume bridge parity until that sub-phase is implemented.

## 2. Architecture constraints

- Windows Agent: C# / .NET 10 / WPF.
- Local Windows storage: SQLite.
- Server: Laravel API, MySQL/MariaDB or PostgreSQL compatible schema where practical.
- API-first architecture.
- Offline-first agent: activity capture must continue without network access.
- Sync must be idempotent.
- Use UUID/ULID client-generated IDs for synchronizable records.
- Server timestamps and device timestamps must both be retained where relevant.
- Raw activity capture and interpreted/project-assigned activity are separate concerns.

## 3. Tracking rules

Primary work-time accounting must use foreground/focused activity, not merely process-open time.

Capture only metadata needed for productivity tracking:

- process name
- executable path where allowed
- foreground window title
- active time range
- idle state
- optional browser URL/domain only via explicit future browser integration

Do **not** implement keylogging, clipboard capture, screenshots, page-content capture, password-field capture, or hidden surveillance.

## 4. Privacy

- Sensitive applications/domains must support exclusion/redaction rules.
- A user must be able to pause tracking.
- Deleted/redacted raw metadata must not silently reappear from derived records.
- Prefer domain/title classification over storing full URLs.


### Additive overlap is a non-negotiable invariant

- Never cap project effort to wall-clock elapsed time.
- Never deduplicate legitimate overlapping activities, even for the same user, device, project, and identical time range.
- Example: a 20-minute phone call plus 20 minutes of coding from 10:00–10:20 for the same project equals 40 minutes Effort and 20 minutes Elapsed Coverage.
- Reports must distinguish **Effort** (sum) from **Elapsed Coverage** (interval union).
- `Effort - Elapsed Coverage` may be shown as concurrent effort but must never be interpreted as a productivity score.

## 5. Time model

Every time entry must have a source:

- `auto_foreground`
- `manual_timer`
- `manual_entry`
- `idle_reclassified`
- future: `calendar`, `git`, `browser_extension`

Never infer that an open application equals active work.

Manual entries are authoritative user input. Multiple manual timers may run concurrently and must not suppress foreground capture or each other. Automatic correction must never overwrite them without explicit action.

## 6. Project classification

Project Resolver must support deterministic weighted rules before AI classification.

Rule examples:

- repository/path match
- window-title match
- executable/process match
- browser domain match
- keyword match

Store classification confidence and the rule/reason that produced it.

User correction must be retained and may optionally create/reinforce a deterministic rule.

## 7. Sync contract

- Client record IDs are generated before upload.
- Re-sending the same record must not duplicate it.
- Sync is append/update by record version, not blind insert.
- Keep a local outbox.
- Mark synchronized items only after server acknowledgement.
- Conflict policy is documented in `docs/api/sync-contract.md`.

## 8. Database evolution

- Never edit an already-released migration to change production behavior; add a new migration.
- Every schema change must update `docs/architecture/data-model.md`.
- Use foreign keys where lifecycle semantics are clear.
- Activity history must be auditable.

## 9. UI/UX

- Persian/RTL support is a first-class requirement even if code identifiers remain English.
- Desktop UI must work without vertical scrolling at common 1366x768 resolutions for core dashboard interactions.
- Prefer global reusable components/styles over page-local copies.
- The System Tray state must clearly indicate tracking / paused / offline-sync-pending.

## 10. Documentation discipline

Before changing architecture, read:

1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/new-chat-brief.md`
4. relevant ADRs
5. `docs/product/mvp-0.1.md`

After meaningful development:

- update `docs/status.md`
- update the roadmap if scope changes
- add an ADR for architectural decisions
- update API/data model docs
- update `docs/handoff/new-chat-brief.md` before handing off to another chat/agent

## 11. Quality gates

No feature is complete until:

- error paths are handled
- offline behavior is considered
- sync idempotency is preserved
- device/user ownership is preserved
- tests cover critical business rules
- documentation reflects the change

## 12. Forbidden shortcuts

Do not:

- deduplicate time across devices
- store activity only on the server
- make network connectivity required for tracking
- use process uptime as working time
- hide classification uncertainty
- silently discard unknown activities
- silently overwrite user corrections
- add AI as a hard dependency for activity capture


## Alpha.2 project-classification invariants
- Project classification is deterministic and rule-based; AI must not silently change attribution.
- Ambiguous classification remains Unknown. False attribution is worse than an Unknown item.
- `priority` is part of resolution; equal-priority near-ties must remain Unknown.
- User correction may assign a project without creating a rule. Rule learning must be explicit (`Assign + Learn`).
- Corrections must never alter activity timestamps, duration, overlap, Effort, or Elapsed Coverage semantics.
- Projects and project rules are offline-first entities with client-generated UUIDs. Do not change them back to server-only ULIDs.
- Manual timers may be assigned to a project at start and remain additive with automatic capture and other manual timers.

## Alpha.3 sync invariants (mandatory)
- Local capture must commit before any network request.
- Never make foreground tracking depend on API availability.
- Every syncable local mutation must produce/update an outbox record transactionally.
- Sanctum tokens must never be stored plaintext; Windows uses DPAPI CurrentUser.
- `device_id` is stable and user-bound. Revoked devices must not sync.
- Sync cursors are opaque client values; never derive business meaning from them on the client.
- Activity-session conflicts must never be auto-merged, auto-normalized or silently discarded.
- Project/Rule pull may update classification configuration but must not inject another device's Activity timeline into the local timeline.
- Additive overlap accounting from ADR 0004 always wins over assumptions that total effort must be <= elapsed time.

## Alpha.4 central-operations invariants (mandatory)
- Central reports must expose Effort and Elapsed Coverage separately; never normalize Effort.
- Device/operator breakdowns are required wherever team/device parallelism can affect interpretation.
- `operator_label` is descriptive metadata, not identity or authorization.
- Sync conflicts are durable server records. Store both client and server payload snapshots.
- Activity conflicts are never auto-merged. Resolution requires explicit `keep_server` or `accept_client`.
- Conflict resolution must use the latest locked server version; never overwrite a newer server version based on stale conflict metadata.
- Resolved conflicts are delivered back to the originating device and acknowledged by conflict id.
- Revocation is enforced at sync time and must not delete local captured history.


## Alpha.4.1 public-host security invariants (mandatory)
- Treat the Laravel host as internet-exposed; do not add anonymous WorkTracker dashboard or API routes.
- `/worktracker/*` requires Session Auth plus WorkTracker admin authorization; fail closed by default.
- `/api/v1/*` is bearer-token-only even if Sanctum SPA/session authentication is available.
- Device tokens use least privilege and must be bound to one exact `device:{uuid}` ability.
- A Device Token must never gain report, operations, conflict-resolution, or general project CRUD access.
- Administrative reads require `admin:read` or `admin:write`; mutations require `admin:write`.
- Do not put tokens in URLs/logs/source. Sanctum stores hashes; Windows stores the plaintext credential only under DPAPI CurrentUser protection.
- Token revocation and Device revocation are independent controls and both must be enforced.
- Production WorkTracker traffic requires HTTPS; fix trusted proxy configuration rather than weakening transport security.

## Deployment and UI consolidation rules (alpha.4.2+)
- Public Laravel deployments MUST point the domain/subdomain document root at Laravel `public/`; never expose project root.
- WorkTracker routes SHOULD be loaded through `App\Providers\WorkTrackerServiceProvider`; avoid copying routes into unrelated route files.
- Sync envelope IDs are authoritative. Do not make ActivitySession primary keys mass-assignable from payloads.
- Validate entity-specific sync payloads before persistence.
- Web pages MUST use the shared WorkTracker Blade layout/components; do not create page-local duplicate design systems.
- Windows UI MUST consume `Themes/WorkTrackerTheme.xaml`. Split growing functional surfaces into reusable UserControls/ViewModels once build verification is available.


## Billing invariants (alpha.5+)
- Pricing is activity-centric.
- Effective hourly rate = base activity rate × customer multiplier × project multiplier.
- Customer and project multipliers default to 1.0000 and multiply together.
- Project+activity explicit rate overrides customer+activity explicit rate.
- Explicit rate overrides are final; do not multiply them again.
- Billing uses additive Effort, never normalized wall-clock coverage.
- Pricing changes must be effective-dated; finalized invoices must snapshot pricing inputs.
- Store monetary amounts as integers, never binary floating point.


## Invoice invariants (alpha.6+)
- Activity Types are pulled configuration; Windows must remain usable offline after a successful sync.
- Laravel is authoritative for monetary calculations.
- Draft invoices may be rebuilt; finalized invoices are immutable through application services.
- Finalization must create immutable pricing snapshots for every invoiced Activity.
- An Activity already billed on a finalized invoice must not be silently billed again.
- Invoice period boundary clipping must not alter the underlying Activity record.
- Billing uses additive Effort; never cap invoice line time to elapsed coverage.
- Keep `docs/handoff/current-project-map.md` synchronized with real repository paths whenever modules move.

- Base rates and customer/project multipliers are effective-dated; never price historical Activities from current columns alone.
- Device Tokens may Pull Billing metadata but must not Push/author commercial rates, customer factors, or project factors.
- Draft invoice generation must visibly count Activities excluded for missing Activity Type or Non-billable status.

## Historical edit and UI invariants (alpha.7+)
- Historical Activity edits require a human-readable reason and an Audit Log entry.
- Activities with finalized billing snapshots must not be directly edited.
- Range reports must show Effort, Coverage and Concurrent Effort separately.
- Visual timelines must render overlaps; never collapse them into one bar.
- New Laravel UI must use shared WorkTracker Blade components and remain usable at 360px width.
- New WPF feature sections should be UserControls/ViewModels rather than expanding MainWindow indefinitely.

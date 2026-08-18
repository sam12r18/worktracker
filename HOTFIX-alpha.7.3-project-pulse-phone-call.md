# WorkTracker alpha.7.3 — Project Pulse quick phone-call timer

This patch extends the verified Project Pulse widget with a project-aware parallel phone-call timer.

## Behavior

- `☎` starts a phone call for the currently classified/green foreground Project.
- `■` ends the active quick call.
- The call runs concurrently with automatic foreground tracking and therefore preserves additive Effort semantics.
- The call is persisted as `manual_timer`, queued to Sync and immediately triggers a Sync attempt after stop.
- The compact/recent list now exposes up to four items.
- While active, the call gets its own `☎ Project` live row and the global Effort/Concurrent counters include its provisional time without prematurely persisting it.
- Only one quick phone call can be active at once.
- If no active Project is classified, the start-call action is disabled.
- The Agent tries to bind a server-managed phone/call Activity Type when one is available; otherwise Activity Type remains unset for later correction.

No Laravel migration is required.

## Validation checklist

1. Build the Windows Agent and confirm the existing Activity Intelligence self-tests still pass.
2. With a classified green Project active, press `☎` and verify a `☎ Project` row appears and increments every second.
3. Keep working in the same Project during the call: Project Effort should increase additively while Coverage remains wall-clock based.
4. Switch foreground to another Project during the call: the call remains assigned to its original Project.
5. Press `■`: one `manual_timer` Activity Session is persisted with note/title `تماس تلفنی`, added to the Sync outbox and an immediate Sync attempt is triggered.
6. Verify `manual.phone` logs contain start and stop records with Project and duration.
7. Verify the Pulse list shows up to four items and compact layout remains usable.
8. When no classified live Project exists, `☎` must be disabled and must never create an unassigned call accidentally.

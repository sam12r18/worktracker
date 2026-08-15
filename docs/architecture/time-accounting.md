# Time Accounting Rules

## Core rule: activity effort is additive

WorkTracker models two different quantities and must never conflate them:

- **Effort**: sum of the durations of all valid activity records. Overlap is retained and additive.
- **Elapsed Coverage**: union of activity intervals on the requested reporting scope. Overlap counts once.

Therefore `Effort` may be greater than elapsed wall-clock time.

### Same project / same device / same user overlap

Example:

- TMS phone call 10:00–10:20 = 20 min
- TMS coding 10:00–10:20 = 20 min

Result: `Effort = 40 min`, `Elapsed Coverage = 20 min`, `Concurrent Effort = 20 min`. Both records are valid and remain independently queryable.

### Multiple projects during one wall-clock hour

A 60-minute wall-clock interval may legitimately contain 45 minutes for Project A, 30 minutes for Project B, and 25 minutes for Project C. Effort is 100 minutes. Do not normalize it back to 60 minutes.

### Multiple devices

No cross-device deduplication is permitted. Two devices can represent two people or concurrent independent work. Device and user identity are always preserved.

## Automatic foreground stream

A Windows desktop has one foreground window at a time. The auto-foreground tracker is therefore a single sequential stream on each device. It closes the current segment before opening the next. This rule prevents tracker-generated duplicate auto segments; it does **not** prohibit overlap with manual, call, meeting, background-job, browser/IDE integration, or corrected activities.

## Manual timers

Multiple manual timers may run concurrently. Starting a manual timer never pauses automatic capture and never pauses another manual timer.

## Reporting

Reports should expose at minimum:

- Effort
- Elapsed Coverage
- Concurrent Effort (`max(0, Effort - Elapsed Coverage)`)
- breakdown by project
- breakdown by source/activity type
- device/user dimensions

Concurrency metrics are descriptive. Do not label them as productivity, efficiency, or performance scores.

## Idle

After the configured idle threshold, foreground auto tracking stops accumulating active foreground time. Manual/call/meeting activities may continue if explicitly active because a user can be working away from keyboard.

# Test Plan — 0.1

## Capture
- switching foreground windows closes/opens sessions correctly
- process exits during inspection without crashing tracker
- inaccessible executable path is tolerated
- Unicode/Persian window titles are preserved
- empty window title is accepted

## Idle
- idle threshold stops active accumulation
- resume creates a new active segment
- idle interval can be reclassified manually

## Time accounting
- overlapping automatic sessions on the same device are rejected/prevented
- overlapping sessions on different devices are preserved
- two devices each tracking 60 minutes yield 120 person-minutes
- manual overlap is preserved with source labels

## Offline/sync
- app captures with API unavailable
- outbox retries safely
- same activity/version sent twice creates one server record
- higher client version updates record
- server-higher version produces conflict
- wrong device ownership is rejected

## Privacy
- excluded process metadata never enters sync outbox
- redacted-title rules persist only redacted title
- pause tracking creates no foreground activity sessions

## Additive overlap accounting

- same user + same device + same project, call 10:00–10:20 plus coding 10:00–10:20 => Effort 2400s, Elapsed Coverage 1200s, Concurrent Effort 1200s
- overlapping activities on different projects remain independently additive
- multiple manual timers can run while foreground capture continues
- server and Windows summaries must return identical values for the same interval set
- no reporting path may cap Effort to wall-clock elapsed time

# alpha.7.3 Activity Intelligence Smoke Test

## Automated build gate

`tools/build-windows-agent.ps1` now runs the deterministic Activity Intelligence self-test after the Release build. Expected final line:

```text
PASS: Activity Intelligence deterministic scenarios
```

The deterministic matrix covers same-Project title aggregation, the 60-second initial anchor, below-threshold anchor rejection, mutual A/B bridges, overlapping three-Project continuity, 120-second per-Project re-arm, >120-second interruption rejection and unobserved-gap rejection.

## 1. PhpStorm file switching

1. Ensure a Project Rule can classify `Ketabnow2` from PhpStorm title.
2. Open at least four files in the same PhpStorm workspace for 5–10 seconds each.
3. Open WPF `Timeline امروز`.

Expected: raw capture may contain multiple sessions, but WPF shows one Work Event for the contiguous Project context and `جزئیات` shows multiple aggregated segments.

## 2. Same Project across apps

1. Work in PhpStorm on Project A for at least 60 seconds.
2. Open a browser page that is also classified as Project A for 30–90 seconds.
3. Return to PhpStorm Project A.

Expected: one Project A Work Event; browser time is direct time and is not double-counted.

## 3. Short cross-Project bridge

1. Work on Project A for at least 60 seconds.
2. Switch to a correctly classified Project B for 20–120 seconds.
3. Return to Project A.

Expected: Project A event shows `مستقیم ... + تداوم ...`; Project B keeps its own direct event; Effort exceeds Coverage by the bridge duration.

## 4. Mutual bridge

Perform:

```text
10:00–10:10 A
10:10–10:11 B
10:11–10:12 A
10:12–10:20 B
10:20–10:30 A
```

Expected:

- A = 22m credited (21m direct + 1m bridge)
- B = 10m credited (9m direct + 1m bridge)
- Coverage = 30m
- Effort = 32m

The B bridge across the short A span is valid even though A also used a bridge earlier. There is no global bridge lock.

## 5. Multi-project overlap

Rotate among three already-established Projects with observed 30–80 second interruptions and return within 120 seconds. Each Project must be evaluated independently. If each chain satisfies its own initial/re-arm rule, two or three Projects may receive continuity credit over overlapping wall-clock intervals. Do not normalize the resulting Effort.

## 6. Re-arm is per Project

After Project A uses a valid bridge, give A less than 120 seconds of new direct foreground work and interrupt A again for <=120 seconds.

Expected: A does not receive the second bridge. This restriction applies only to A; another Project may independently bridge during the same timeline.

## 7. Long interruption

Repeat test 3 but remain on Project B for more than 120 seconds.

Expected: no bridge; Project A is split into separate events.

## 8. Unknown IDE aggregation + learning

1. Disable/delete the Project Rule for a PhpStorm workspace.
2. Switch among several files.
3. In `تشخیص‌داده‌نشده`, select the aggregated event and choose `انتساب + یادگیری`.

Expected: all raw sessions in the event are assigned together and the learned WindowTitle Rule uses the stable workspace name rather than the current file name.

## 9. Project Pulse widget

1. Start a classified Project and leave the same foreground context active for at least 20 seconds.
2. Watch the small side widget.
3. Switch through two other Projects and return.

Expected: the active Project duration advances live before its raw session is flushed to SQLite; the three most recent Projects are ranked by current/recent activity; direct and bridge counters remain separate; global Effort/Coverage/Concurrent agree with the local projection.

## 10. Activity Type inference safety

- A plain PhpStorm title must remain without an automatically inferred Development type.
- If an explicit Debug/Debugger title signal exists and an active Debug/دیباگ Activity Type exists locally, that type may be inferred.

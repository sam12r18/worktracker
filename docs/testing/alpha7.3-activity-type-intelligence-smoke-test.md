# alpha.7.3 P1 — Activity Type Intelligence smoke test

## Preconditions

- Laravel migrations are current.
- Windows Agent builds successfully and its Activity Intelligence self-test passes.
- Activity Types include at least Development, Debugging and Testing.
- The test Project has a valid Project Rule so the Agent can identify it.

## 1. Project default fallback

1. In Web → Projects → target Project, set **Default Activity Type = Development**.
2. Sync the Agent or use **Full configuration refresh**.
3. Work in normal PhpStorm editor tabs for at least 20 seconds.
4. Leave the window so the raw segment is flushed.

Expected:

- Activity is assigned to the Project.
- Activity Type is Development.
- `activity_type_source=project_default`.
- `activity_type_confidence=0.72`.
- `activity.type` Agent log explains `project_default_activity_type`.

## 2. Explicit Debug signal overrides Project default

Use a clearly identifiable Debugger window/title (not merely a source file whose filename contains `Debug`).

Expected:

- Debugging overrides Development.
- `activity_type_source=ide_signal`.
- Confidence is approximately 0.99.

A file such as `DebugService.php` by itself must **not** trigger Debugging.

## 3. Testing signal

Open an IDE context whose title clearly exposes `PHPUnit`, `Test Runner` or `Test Results`.

Expected:

- Activity Type becomes Testing if a matching Activity Type exists.
- Source is `ide_signal`.

## 4. Configurable rule

1. Web → Activity Intelligence.
2. Add a Project-scoped rule:
   - type: `ProcessName`
   - operator: `equals`
   - pattern: `phpstorm64`
   - Activity Type: Development
   - weight: 80
   - priority: 10
   - confidence: 0.90
3. Sync the Agent.
4. Temporarily remove the Project default and work in PhpStorm.

Expected:

- Agent reports at least one local Activity Type Rule in Sync configuration status.
- New Activity gets Development from `activity_type_source=rule`.
- Agent log lists the matched rule.

## 5. Ambiguity safety

Create two global rules with the same priority and close scores that map the same process to different Activity Types.

Expected:

- Resolver does not guess when the winning margin is below the minimum threshold.
- If there is no Project default, Activity Type remains Unknown.

## 6. Manual override provenance

Change an Activity Type manually in WPF or historical Activities in Web.

Expected:

- `activity_type_source=user_override`.
- `activity_type_confidence=1.0`.
- The correction syncs to Laravel.

## 7. Work Event audit

Open Web → Work Events and expand the Event.

Expected:

- Raw segments display Activity Type and its source/confidence when available.
- Direct/Bridge/Credited totals are unchanged by Activity Type classification.

## 8. Rule deactivation sync

Deactivate a rule in Web, then sync the Agent.

Expected:

- Rule remains as a disabled server row/tombstone.
- Agent no longer uses it for new classifications.
- No stale deleted rule remains active on the device.


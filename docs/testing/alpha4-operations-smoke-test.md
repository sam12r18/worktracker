# Alpha.4 Operations Smoke Test

## Server setup
1. Apply all migrations through `2026_08_11_001200_add_operations_and_sync_conflicts.php`.
2. Ensure Sanctum authentication is active for `/api/v1/*`.
3. Include `routes/worktracker.php` from the host application's web routing if the Blade panel is desired.
4. Authenticate in the web app and open `/worktracker`.

## Reporting checks
Create overlapping sessions for the same project:
- 10:00–10:20 phone call
- 10:00–10:20 coding
Expected: Effort 2400s, Coverage 1200s, Concurrent Effort 1200s.

Create one 60-minute session on Device A and one overlapping 60-minute session on Device B.
Expected project Effort: 7200s. Device breakdown must retain 3600s for each device.

Test an activity crossing midnight and query a multi-day project report. Each day must receive only its clipped portion.

## Sync health checks
- Sync a Windows agent and verify `last_sync_started_at` and `last_sync_succeeded_at` update.
- Cause a failed request and verify `last_sync_error` is populated.
- Assign `operator_label` in the panel and verify it appears in report device breakdown.
- Revoke a device and verify subsequent sync returns HTTP 403.

## Conflict resolution checks
1. Create server version 2 of an entity while the client sends version 1.
2. Verify a durable `sync_conflicts` row exists with both payload snapshots.
3. Resolve with `keep_server`; next device sync must receive the resolution, mark the local conflict resolved, and queue an acknowledgement.
4. Sync again; server `acknowledged_at` must be populated.
5. Repeat with `accept_client`; server must apply client payload using a new version greater than the current server version.
6. For ActivitySession conflicts, confirm no automatic merge or time normalization occurs.

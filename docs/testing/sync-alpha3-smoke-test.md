# alpha.3 Sync Smoke Test

Run against a real Laravel host with Sanctum and all WorkTracker migrations applied.

## A. First device registration
1. Build/run Windows Agent.
2. Open `همگام‌سازی`.
3. Enter server root URL and a valid Sanctum token.
4. Click `ذخیره و تست اتصال`.
5. Verify the server `devices` row has the same local device UUID and `last_seen_at` updates.

## B. Offline capture → online push
1. Disconnect network.
2. Capture at least one foreground session, one manual session, create one project and one Rule.
3. Verify Pending Sync grows and capture remains functional.
4. Reconnect network and click `Sync اکنون`.
5. Verify outbox drains and Laravel contains all records with original UUIDs and versions.

## C. Additive time invariant through sync
Create 10:00–10:20 phone/manual activity and 10:00–10:20 coding activity for the same project/device. Verify server stores both rows. Effort must remain 40m and coverage 20m. No sync layer may shorten, merge or delete either row.

## D. Second-device Project/Rule pull
1. Configure a second Windows installation with the same Laravel user but a different device UUID.
2. Sync.
3. Verify Project/Rule configuration from device A appears locally on B.
4. Verify A's Activity Sessions do **not** appear in B's local Timeline.

## E. Idempotency
Replay sync without local changes. Verify no duplicate projects/rules/activity sessions are created.

## F. Retry
Stop the API or use an invalid host. Verify capture continues, outbox attempt_count increases, next_attempt_at is populated, and later recovery drains the queue.

## G. Revocation
Set `devices.revoked_at` on the server. Verify subsequent sync returns 403 while local capture continues.

## H. Conflict safety
Create a server version newer than a queued local entity. Sync and verify `sync_conflicts` receives a row. For Activity Session conflicts verify the local activity remains present and is not rewritten.

## I. Cursor pagination
Create more configuration changes than `pull_limit`, including multiple records with identical `updated_at` timestamps. Repeated syncs must eventually retrieve every Project/Rule exactly once logically (replays are harmless) without gaps.

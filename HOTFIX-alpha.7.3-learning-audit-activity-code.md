# WorkTracker alpha.7.3 — Learning, Audit and Activity Type edit reliability patch

This patch addresses four issues found during real-world alpha.7.3 use:

1. `WorkTrackerAuditLog` now explicitly maps to the existing `worktracker_audit_logs` table created by the original migration. Laravel's inferred `work_tracker_audit_logs` name was incorrect and caused `/worktracker/audit` to fail.
2. User-owned Activity Types can now edit `code` as well as title/rate/currency/status. The UUID remains stable, so existing Activity Sessions, pricing history and rules keep their relationships.
3. Browser **Assign + Learn** can derive a safe segmented title pattern such as `Ketabnow` from an explicit user-selected tab title. Learning success/failure is visible to the user and written to `classification.learn`; successful learned Project Rules are immediately synced and Project Rule outbox rows are prioritized.
4. Sync acknowledgement now carries `client_outbox_id` end-to-end. This removes the observed `matched: 0 / whole_batch_fallback: true` behavior for current Agent/server pairs and acknowledges the exact SQLite queue row.

No Laravel migration is required for this patch. Run `php artisan optimize:clear` after deployment and rebuild the Windows Agent.

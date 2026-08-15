# ADR 0002 — Offline-first Windows Agent

Status: **Accepted**

The tracker must continue capturing when the server or internet is unavailable. All capture is committed to SQLite first and sent through an idempotent outbox. The Laravel API is central storage, not a runtime dependency for activity capture.

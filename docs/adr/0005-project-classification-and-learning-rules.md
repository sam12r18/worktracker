# ADR 0005 — Project classification and correction learning

Status: Accepted for 0.1.0-alpha.2

## Decision
Foreground activities are classified locally from deterministic weighted rules. Unknown activities are never discarded. The user can assign a project later and optionally create a reusable rule from that correction.

## Rules
- Classification runs when an auto foreground session is flushed.
- Rules are offline-first and syncable.
- A correction changes only project attribution; it never changes timestamps or additive time accounting.
- "Assign + learn" currently learns from normalized window title, with process/path fallbacks.
- Low-confidence or no-match activity remains in Unknown Inbox.
- Rule learning is explicit; user correction does not silently create rules.

## IDs
Projects and project rules use client-generated UUIDs so they can be created offline and synchronized from any Windows agent.

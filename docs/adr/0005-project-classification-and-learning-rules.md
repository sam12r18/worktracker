# ADR 0005 — Project classification and correction learning

Status: Accepted for 0.1.0-alpha.2

## Decision
Foreground activities are classified locally from deterministic weighted rules. Unknown activities are never discarded. The user can assign a project later and optionally create a reusable rule from that correction.

## Rules
- Classification runs when an auto foreground session is flushed.
- Rules are offline-first and syncable.
- A correction changes only project attribution; it never changes timestamps or additive time accounting.
- "Assign + learn" is Work-Event aware. For known IDEs it learns the stable workspace/title pattern instead of one exact file title; process/path fallbacks remain available.
- Low-confidence or no-match activity remains in Unknown Inbox.
- Rule learning is explicit; user correction does not silently create rules.

## IDs
Projects and project rules use client-generated UUIDs so they can be created offline and synchronized from any Windows agent.

## Rule operators (alpha.7.2)

The Windows resolver now executes the server-side `operator` field (`contains`, `equals`, `starts_with`, `ends_with`, `regex`). Regex evaluation is case-insensitive and time-bounded; invalid or timed-out regex rules are treated as non-matches rather than crashing tracking. Existing local databases receive an `operator` column defaulted to `contains`.

## alpha.7.3 context-aware learning

Window title is not treated as a semantic activity boundary. `ActivityContextNormalizer` extracts stable IDE workspace context and WPF correction acts on the aggregated Work Event. The web Rule Builder can suggest a stable pattern from a sample title and preview matches against recent activity so broad/unsafe Rules are visible before saving.

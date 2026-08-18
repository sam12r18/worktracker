# WorkTracker alpha.7.3 — call-meeting Activity Type hotfix

The Project Pulse quick phone-call action now recognizes the server-managed Activity Type code `call-meeting` as the preferred call/meeting classification.

No Laravel migration or database change is required. Existing fallback codes (`phone_call`, `call`, `phone`, etc.) remain supported for compatibility.

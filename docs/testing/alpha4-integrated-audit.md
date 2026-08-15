# Integrated audit — alpha.1 through alpha.4.2

## Invariants rechecked
- Activity overlap remains additive, including same user/device/project overlap.
- Cross-device activity is never deduplicated.
- Manual timers do not pause foreground capture.
- Unknown classification is preferred to ambiguous auto-assignment.
- Device API tokens cannot access admin APIs.
- Device token is bound to one device UUID.
- Activity sessions are not pulled into another device's local timeline.

## Issues found and corrected in alpha.4.2
1. Windows device registration reported the old alpha.4 version string.
2. Sync entity payloads were only envelope-validated; malformed activity/project/rule payloads could reach Eloquent/database validation late.
3. `ActivitySession.id` was mass assignable even though the sync envelope is the authoritative ID.
4. WorkTracker route installation required manual route merging, increasing deployment mistakes. A dedicated `WorkTrackerServiceProvider` and `routes/worktracker-api.php` were added.
5. cPanel/local deployment requirements were incomplete; explicit PHP/public-root/Sanctum/Auth/HTTPS instructions and a server preflight were added.
6. Blade pages duplicated their design primitives and tables could overflow narrow screens. Shared layout/components and responsive table containment were added.
7. WPF styles were partly page-local and minimum window size was unnecessarily large. Global theme resources and safer minimum dimensions were added.

## Known verification boundary
The current environment does not contain the .NET 10 SDK, Composer, or a real MySQL/MariaDB Laravel host. Therefore WPF compilation and true cPanel/MySQL migration execution are not claimed as completed. They remain mandatory smoke tests on the target environment.

# Privacy and Data Policy

WorkTracker is a productivity tracker, not an employee-surveillance product.

## Captured by default

- foreground process name
- executable path when accessible
- foreground window title
- activity start/end timestamps
- idle duration/state
- assigned project and classification reason
- device identity

## Explicitly not captured

- keystrokes
- clipboard contents
- screenshots/screen recordings
- document/page bodies
- passwords or form fields
- microphone/camera

## User controls

- pause/resume tracking
- exclude applications
- redact window titles
- future: exclude/redact browser domains
- edit project assignment
- delete or redact captured metadata subject to audit policy

Sensitive-data exclusions must run as close to capture as possible so excluded metadata is not uploaded first and redacted later.

## IDE metadata in alpha.8
The optional PhpStorm Context Bridge records metadata, not source contents. The allowed protocol fields are IDE/plugin version, IDE process ID, Project name/path, active file name/path, Git branch, execution mode, Run Configuration, and observation timestamp. WorkTracker must not collect source text, console output, debugger values, environment variables, Git credentials, or API tokens through this bridge.

The heartbeat file is scoped to the current OS user under `%LOCALAPPDATA%\WorkTracker\ide\phpstorm`. The Agent removes very stale heartbeat files on a best-effort basis and continues normal capture if the plugin is absent. Server-side storage of Project/file paths is intended for audit and future rule/context analysis; a later alpha.8 privacy-control slice should add user-configurable allow/deny policies before expanding IDE metadata further.

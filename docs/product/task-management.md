# Task Management

WorkTracker is not only a tracker; projects may contain actionable work items.

## MVP task fields

- project
- optional parent task
- title
- description
- status: backlog / planned / in_progress / blocked / done / cancelled
- priority: low / normal / high / urgent
- due_at optional
- started_at / completed_at optional
- estimated_minutes optional
- assignee user optional (future team-ready)
- sort order

An activity session may optionally reference a task. A timer can therefore run against either a project or a specific task.

## Key rule

Task status and time tracking are separate state machines. Finishing a timer does not automatically complete a task; completing a task does not invent time.

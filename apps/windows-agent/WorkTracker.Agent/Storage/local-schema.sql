PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS device_state (
    key TEXT PRIMARY KEY,
    value TEXT NULL
);

CREATE TABLE IF NOT EXISTS projects (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    code TEXT NULL,
    parent_id TEXT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    version INTEGER NOT NULL DEFAULT 1,
    sync_state TEXT NOT NULL DEFAULT 'pending',
    updated_at TEXT NOT NULL,
    customer_id TEXT NULL,
    rate_multiplier REAL NOT NULL DEFAULT 1.0,
    is_billable_default INTEGER NOT NULL DEFAULT 1,
    default_activity_type_id TEXT NULL
);

CREATE TABLE IF NOT EXISTS project_rules (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL,
    rule_type TEXT NOT NULL,
    operator TEXT NOT NULL DEFAULT 'contains',
    pattern TEXT NOT NULL,
    weight INTEGER NOT NULL,
    priority INTEGER NOT NULL DEFAULT 0,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    version INTEGER NOT NULL DEFAULT 1,
    sync_state TEXT NOT NULL DEFAULT 'pending',
    updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_project_rules_resolution ON project_rules(is_enabled, priority, weight);

CREATE TABLE IF NOT EXISTS activity_sessions (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    device_id TEXT NOT NULL,
    project_id TEXT NULL,
    task_id TEXT NULL,
    activity_type_id TEXT NULL,
    activity_type_confidence REAL NULL,
    activity_type_source TEXT NULL,
    activity_type_reason TEXT NULL,
    ide_context_json TEXT NULL,
    browser_context_json TEXT NULL,
    is_billable INTEGER NULL,
    source TEXT NOT NULL,
    process_name TEXT NULL,
    executable_path TEXT NULL,
    window_title TEXT NULL,
    classification_confidence REAL NULL,
    classification_reason TEXT NULL,
    started_at TEXT NOT NULL,
    ended_at TEXT NOT NULL,
    duration_seconds INTEGER NOT NULL,
    idle_seconds INTEGER NOT NULL DEFAULT 0,
    note TEXT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    sync_state TEXT NOT NULL DEFAULT 'pending',
    created_at_device TEXT NOT NULL,
    updated_at_device TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_activity_device_time ON activity_sessions(device_id, started_at, ended_at);
CREATE INDEX IF NOT EXISTS idx_activity_project_time ON activity_sessions(project_id, started_at);
CREATE INDEX IF NOT EXISTS idx_activity_sync ON activity_sessions(sync_state);

CREATE TABLE IF NOT EXISTS sync_outbox (
    id TEXT PRIMARY KEY,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    operation TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    next_attempt_at TEXT NULL,
    last_error TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sync_conflicts (
    id TEXT PRIMARY KEY,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    server_version INTEGER NOT NULL,
    reason TEXT NULL,
    created_at TEXT NOT NULL,
    resolved_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_sync_conflicts_open ON sync_conflicts(resolved_at, entity_type, entity_id);

CREATE TABLE IF NOT EXISTS sync_resolution_acks (
    conflict_id TEXT PRIMARY KEY,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS activity_types (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    is_billable_default INTEGER NOT NULL DEFAULT 1,
    base_hourly_rate_minor INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'IRT',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    version INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_activity_types_active ON activity_types(is_active, sort_order, name);

CREATE TABLE IF NOT EXISTS activity_type_rules (
    id TEXT PRIMARY KEY,
    project_id TEXT NULL,
    activity_type_id TEXT NOT NULL,
    rule_type TEXT NOT NULL,
    operator TEXT NOT NULL DEFAULT 'contains',
    pattern TEXT NOT NULL,
    weight INTEGER NOT NULL DEFAULT 80,
    priority INTEGER NOT NULL DEFAULT 0,
    confidence REAL NOT NULL DEFAULT 0.9,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    version INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_activity_type_rules_resolution ON activity_type_rules(is_enabled,priority,weight);
CREATE INDEX IF NOT EXISTS idx_activity_type_rules_project ON activity_type_rules(project_id,is_enabled);

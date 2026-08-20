using System.IO;
using Microsoft.Data.Sqlite;

namespace WorkTracker.Agent.Storage;

public sealed class LocalDatabase
{
    private readonly string _connectionString;

    public LocalDatabase(string? databasePath = null)
    {
        var root = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "WorkTracker");
        Directory.CreateDirectory(root);
        DatabasePath = databasePath ?? Path.Combine(root, "worktracker.db");
        _connectionString = new SqliteConnectionStringBuilder { DataSource = DatabasePath, ForeignKeys = true }.ToString();
    }

    public string DatabasePath { get; }

    public SqliteConnection OpenConnection()
    {
        var connection = new SqliteConnection(_connectionString);
        connection.Open();
        return connection;
    }

    public async Task InitializeAsync(CancellationToken cancellationToken = default)
    {
        await using var connection = OpenConnection();
        var schemaPath = Path.Combine(AppContext.BaseDirectory, "Storage", "local-schema.sql");
        var sql = await File.ReadAllTextAsync(schemaPath, cancellationToken);
        await using (var command = connection.CreateCommand())
        {
            command.CommandText = sql;
            await command.ExecuteNonQueryAsync(cancellationToken);
        }

        await EnsureColumnAsync(connection, "project_rules", "operator", "TEXT NOT NULL DEFAULT 'contains'", cancellationToken);
        await EnsureColumnAsync(connection, "projects", "customer_id", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "projects", "rate_multiplier", "REAL NOT NULL DEFAULT 1.0", cancellationToken);
        await EnsureColumnAsync(connection, "projects", "is_billable_default", "INTEGER NOT NULL DEFAULT 1", cancellationToken);
        await EnsureColumnAsync(connection, "projects", "default_activity_type_id", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "activity_type_id", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "activity_type_confidence", "REAL NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "activity_type_source", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "activity_type_reason", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "ide_context_json", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "browser_context_json", "TEXT NULL", cancellationToken);
        await EnsureColumnAsync(connection, "activity_sessions", "is_billable", "INTEGER NULL", cancellationToken);
    }

    private static async Task EnsureColumnAsync(SqliteConnection connection, string table, string column, string definition, CancellationToken ct)
    {
        await using var info = connection.CreateCommand();
        info.CommandText = $"PRAGMA table_info({table});";
        await using var reader = await info.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
            if (string.Equals(reader.GetString(1), column, StringComparison.OrdinalIgnoreCase)) return;

        await reader.DisposeAsync();
        await using var alter = connection.CreateCommand();
        alter.CommandText = $"ALTER TABLE {table} ADD COLUMN {column} {definition};";
        await alter.ExecuteNonQueryAsync(ct);
    }
}

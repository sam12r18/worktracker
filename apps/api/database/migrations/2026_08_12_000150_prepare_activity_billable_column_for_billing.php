<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // alpha.1 created is_billable, while alpha.5 introduced the nullable billing form of the same column.
        // Fresh installs need the legacy column removed before the alpha.5 migration runs.
        // Existing alpha.5/alpha.6 installs already have 000200 recorded, so this migration must be a no-op there.
        $billingMigrationAlreadyApplied = Schema::hasTable('migrations')
            && DB::table('migrations')->where('migration', '2026_08_12_000200_add_billing_fields_to_projects_and_activities')->exists();

        if (! $billingMigrationAlreadyApplied && Schema::hasColumn('activity_sessions', 'is_billable')) {
            Schema::table('activity_sessions', fn (Blueprint $table) => $table->dropColumn('is_billable'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activity_sessions', 'is_billable')) {
            Schema::table('activity_sessions', fn (Blueprint $table) => $table->boolean('is_billable')->default(false));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Alpha.2 allows projects/rules to be created offline with UUIDs (36 chars).
        // Existing ULIDs (26 chars) remain valid values in the widened columns.
        Schema::table('project_rules', fn (Blueprint $table) => $table->dropForeign(['project_id']));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropForeign(['project_id']));
        Schema::table('activity_sessions', fn (Blueprint $table) => $table->dropForeign(['project_id']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropForeign(['parent_id']));

        Schema::table('projects', function (Blueprint $table) {
            $table->string('id', 36)->change();
            $table->string('parent_id', 36)->nullable()->change();
        });
        Schema::table('project_rules', function (Blueprint $table) {
            $table->string('id', 36)->change();
            $table->string('project_id', 36)->change();
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->string('project_id', 36)->change());
        Schema::table('activity_sessions', fn (Blueprint $table) => $table->string('project_id', 36)->nullable()->change());

        Schema::table('projects', fn (Blueprint $table) => $table->foreign('parent_id')->references('id')->on('projects')->nullOnDelete());
        Schema::table('project_rules', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete());
        Schema::table('activity_sessions', fn (Blueprint $table) => $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete());
    }

    public function down(): void
    {
        // UUID-created projects cannot be losslessly narrowed back to ULID columns.
        // Intentionally non-reversible to protect data integrity.
        throw new RuntimeException('Cannot safely downgrade project identifiers after offline UUID project creation.');
    }
};

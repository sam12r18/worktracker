<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('default_activity_type_id')->nullable()->after('is_billable_default');
            $table->foreign('default_activity_type_id')->references('id')->on('activity_types')->nullOnDelete();
            $table->index(['user_id', 'default_activity_type_id']);
        });

        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->decimal('activity_type_confidence', 5, 4)->nullable()->after('activity_type_id');
            $table->string('activity_type_source', 64)->nullable()->after('activity_type_confidence');
            $table->text('activity_type_reason')->nullable()->after('activity_type_source');
            $table->index(['activity_type_source', 'activity_type_id']);
        });

        Schema::create('activity_type_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_id', 36)->nullable();
            $table->uuid('activity_type_id');
            $table->string('rule_type', 40);
            $table->string('operator', 30)->default('contains');
            $table->text('pattern');
            $table->integer('weight')->default(80);
            $table->integer('priority')->default(0);
            $table->decimal('confidence', 5, 4)->default(0.9000);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->cascadeOnDelete();
            $table->index(['user_id', 'is_enabled', 'priority']);
            $table->index(['project_id', 'is_enabled']);
            $table->index(['updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_type_rules');

        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->dropIndex(['activity_type_source', 'activity_type_id']);
            $table->dropColumn(['activity_type_confidence', 'activity_type_source', 'activity_type_reason']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'default_activity_type_id']);
            $table->dropForeign(['default_activity_type_id']);
            $table->dropColumn('default_activity_type_id');
        });
    }
};

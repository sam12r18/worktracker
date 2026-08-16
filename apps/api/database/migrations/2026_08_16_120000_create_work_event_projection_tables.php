<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_events', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->string('project_id', 36)->nullable();
            $table->date('projection_date');
            $table->string('timezone', 80)->default('Asia/Tehran');
            $table->string('event_kind', 30)->default('foreground');
            $table->string('context_key', 255)->nullable();
            $table->dateTimeTz('started_at');
            $table->dateTimeTz('ended_at');
            $table->unsignedInteger('direct_seconds')->default(0);
            $table->unsignedInteger('bridge_seconds')->default(0);
            $table->unsignedInteger('credited_seconds')->default(0);
            $table->unsignedInteger('segment_count')->default(0);
            $table->unsignedInteger('bridge_count')->default(0);
            $table->json('applications')->nullable();
            $table->string('projection_version', 40);
            $table->dateTimeTz('calculated_at');
            $table->timestampsTz();

            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->index(['user_id', 'projection_date']);
            $table->index(['device_id', 'projection_date']);
            $table->index(['project_id', 'projection_date']);
            $table->index(['user_id', 'started_at', 'ended_at']);
        });

        Schema::create('work_event_segments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('work_event_id', 64);
            $table->uuid('activity_session_id');
            $table->unsignedInteger('position')->default(0);
            $table->dateTimeTz('started_at');
            $table->dateTimeTz('ended_at');
            $table->unsignedInteger('duration_seconds');
            $table->timestampsTz();

            $table->foreign('work_event_id')->references('id')->on('work_events')->cascadeOnDelete();
            $table->foreign('activity_session_id')->references('id')->on('activity_sessions')->cascadeOnDelete();
            $table->unique(['work_event_id', 'activity_session_id'], 'work_event_segment_unique');
            $table->index(['activity_session_id', 'work_event_id']);
        });

        Schema::create('continuity_bridges', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('work_event_id', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->string('anchor_project_id', 36);
            $table->date('projection_date');
            $table->dateTimeTz('started_at');
            $table->dateTimeTz('ended_at');
            $table->unsignedInteger('duration_seconds');
            $table->json('interrupted_project_ids')->nullable();
            $table->string('reason', 80)->default('continuity_restored');
            $table->string('projection_version', 40);
            $table->timestampsTz();

            $table->foreign('work_event_id')->references('id')->on('work_events')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->foreign('anchor_project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['user_id', 'projection_date']);
            $table->index(['anchor_project_id', 'projection_date']);
            $table->index(['device_id', 'started_at', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_bridges');
        Schema::dropIfExists('work_event_segments');
        Schema::dropIfExists('work_events');
    }
};

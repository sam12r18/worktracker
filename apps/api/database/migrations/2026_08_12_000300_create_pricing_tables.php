<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('activity_type_id');
            $table->unsignedBigInteger('hourly_rate_minor');
            $table->string('currency', 8)->default('IRT');
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->cascadeOnDelete();
            $table->index(['user_id', 'activity_type_id', 'effective_from']);
            $table->index(['project_id', 'activity_type_id']);
            $table->index(['customer_id', 'activity_type_id']);
        });

        Schema::create('billing_rate_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_session_id');
            $table->unsignedBigInteger('base_rate_minor');
            $table->decimal('customer_multiplier', 10, 4)->default(1.0000);
            $table->decimal('project_multiplier', 10, 4)->default(1.0000);
            $table->unsignedBigInteger('effective_rate_minor');
            $table->unsignedBigInteger('billable_seconds');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('IRT');
            $table->string('resolution_source', 64);
            $table->uuid('pricing_override_id')->nullable();
            $table->timestamps();

            $table->unique('activity_session_id');
            $table->foreign('activity_session_id')->references('id')->on('activity_sessions')->cascadeOnDelete();
            $table->foreign('pricing_override_id')->references('id')->on('pricing_overrides')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_rate_snapshots');
        Schema::dropIfExists('pricing_overrides');
    }
};

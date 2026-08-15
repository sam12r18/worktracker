<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('currency', 8)->default('IRT');
            $table->decimal('rate_multiplier', 10, 4)->default(1.0000);
            $table->boolean('is_active')->default(true);
            $table->text('billing_notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('is_billable_default')->default(true);
            $table->unsignedBigInteger('base_hourly_rate_minor')->default(0);
            $table->string('currency', 8)->default('IRT');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('customers');
    }
};

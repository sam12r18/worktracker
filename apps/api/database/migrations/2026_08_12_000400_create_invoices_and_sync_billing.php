<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) { $table->unsignedInteger('version')->default(1); });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id'); $table->string('number')->nullable(); $table->string('status',20)->default('draft');
            $table->date('period_start'); $table->date('period_end'); $table->string('currency',8)->default('IRT');
            $table->unsignedBigInteger('subtotal_minor')->default(0); $table->bigInteger('adjustment_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0); $table->unsignedBigInteger('total_minor')->default(0);
            $table->text('notes')->nullable(); $table->timestamp('finalized_at')->nullable(); $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->index(['user_id','customer_id','period_start','period_end']); $table->unique(['user_id','number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('invoice_id'); $table->uuid('activity_session_id');
            $table->uuid('project_id')->nullable(); $table->uuid('activity_type_id')->nullable(); $table->string('description');
            $table->timestamp('started_at'); $table->unsignedInteger('billable_seconds');
            $table->unsignedBigInteger('base_rate_minor'); $table->decimal('customer_multiplier',10,4)->default(1);
            $table->decimal('project_multiplier',10,4)->default(1); $table->unsignedBigInteger('effective_rate_minor');
            $table->unsignedBigInteger('amount_minor'); $table->string('currency',8); $table->string('resolution_source',64);
            $table->uuid('pricing_override_id')->nullable(); $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('activity_session_id')->references('id')->on('activity_sessions')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->nullOnDelete();
            $table->foreign('pricing_override_id')->references('id')->on('pricing_overrides')->nullOnDelete();
            $table->unique(['invoice_id','activity_session_id']); $table->index(['activity_session_id','invoice_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('invoice_items'); Schema::dropIfExists('invoices');
        Schema::table('activity_types', function (Blueprint $table) { $table->dropColumn('version'); });
    }
};

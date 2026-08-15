<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->after('user_id');
            $table->decimal('rate_multiplier', 10, 4)->default(1.0000);
            $table->boolean('is_billable_default')->default(true);
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['customer_id', 'is_billable_default']);
        });

        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->uuid('activity_type_id')->nullable()->after('project_id');
            $table->boolean('is_billable')->nullable();
            $table->foreign('activity_type_id')->references('id')->on('activity_types')->nullOnDelete();
            $table->index(['activity_type_id', 'is_billable']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_sessions', function (Blueprint $table) {
            $table->dropForeign(['activity_type_id']);
            $table->dropColumn(['activity_type_id', 'is_billable']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'rate_multiplier', 'is_billable_default']);
        });
    }
};

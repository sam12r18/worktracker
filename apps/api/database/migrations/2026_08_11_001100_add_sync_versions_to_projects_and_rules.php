<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('is_archived');
            $table->index(['user_id', 'updated_at']);
        });
        Schema::table('project_rules', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('is_enabled');
            $table->index(['updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('project_rules', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
            $table->dropColumn('version');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropColumn('version');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('name');
            $table->string('code', 80)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('color', 20)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
        Schema::create('project_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 40);
            $table->string('operator', 30)->default('contains');
            $table->text('pattern');
            $table->integer('weight')->default(50);
            $table->integer('priority')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->index(['project_id','is_enabled','priority']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('project_rules');
        Schema::dropIfExists('projects');
    }
};

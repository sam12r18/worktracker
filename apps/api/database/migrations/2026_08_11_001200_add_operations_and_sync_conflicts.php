<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('operator_label', 120)->nullable()->after('name');
            $table->timestamp('last_sync_started_at')->nullable()->after('last_seen_at');
            $table->timestamp('last_sync_succeeded_at')->nullable()->after('last_sync_started_at');
            $table->text('last_sync_error')->nullable()->after('last_sync_succeeded_at');
            $table->unsignedInteger('last_sync_pushed')->default(0)->after('last_sync_error');
            $table->unsignedInteger('last_sync_pulled')->default(0)->after('last_sync_pushed');
            $table->index(['user_id', 'last_sync_succeeded_at']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 40);
            $table->string('entity_id', 64);
            $table->unsignedInteger('client_version');
            $table->unsignedInteger('server_version');
            $table->json('client_payload');
            $table->json('server_payload')->nullable();
            $table->string('reason', 80)->default('server_newer');
            $table->string('status', 20)->default('open'); // open|resolved
            $table->string('resolution', 20)->nullable(); // keep_server|accept_client
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'entity_type', 'entity_id', 'client_version'], 'sync_conflicts_client_unique');
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['device_id', 'status', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_sync_succeeded_at']);
            $table->dropColumn([
                'operator_label','last_sync_started_at','last_sync_succeeded_at','last_sync_error',
                'last_sync_pushed','last_sync_pulled'
            ]);
        });
    }
};

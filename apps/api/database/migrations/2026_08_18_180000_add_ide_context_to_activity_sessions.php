<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_sessions', 'ide_context')) {
            Schema::table('activity_sessions', function (Blueprint $table): void {
                $table->json('ide_context')->nullable()->after('activity_type_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_sessions', 'ide_context')) {
            Schema::table('activity_sessions', function (Blueprint $table): void {
                $table->dropColumn('ide_context');
            });
        }
    }
};

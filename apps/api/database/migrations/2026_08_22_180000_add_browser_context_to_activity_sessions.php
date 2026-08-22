<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_sessions', 'browser_context')) {
            Schema::table('activity_sessions', function (Blueprint $table): void {
                $table->json('browser_context')->nullable()->after('ide_context');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_sessions', 'browser_context')) {
            Schema::table('activity_sessions', function (Blueprint $table): void {
                $table->dropColumn('browser_context');
            });
        }
    }
};

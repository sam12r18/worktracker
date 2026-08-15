<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('invoices',function(Blueprint $t){$t->unsignedInteger('untyped_activity_count')->default(0);$t->unsignedInteger('nonbillable_activity_count')->default(0);});} public function down():void{Schema::table('invoices',function(Blueprint $t){$t->dropColumn(['untyped_activity_count','nonbillable_activity_count']);});} };

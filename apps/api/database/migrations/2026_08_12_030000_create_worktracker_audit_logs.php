<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('worktracker_audit_logs', function(Blueprint $t){
  $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
  $t->string('entity_type',60); $t->string('entity_id',64); $t->string('action',60);
  $t->json('before_json')->nullable(); $t->json('after_json')->nullable(); $t->text('reason')->nullable();
  $t->string('ip_address',64)->nullable(); $t->string('user_agent',500)->nullable(); $t->timestamps();
  $t->index(['user_id','entity_type','entity_id']); $t->index(['user_id','created_at']);
 }); }
 public function down():void { Schema::dropIfExists('worktracker_audit_logs'); }
};

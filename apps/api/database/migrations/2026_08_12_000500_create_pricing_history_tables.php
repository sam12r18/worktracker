<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_rate_history', function (Blueprint $t) {
            $t->id();
            $t->uuid('activity_type_id');
            $t->unsignedBigInteger('hourly_rate_minor');
            $t->string('currency', 8);
            $t->boolean('is_billable_default')->default(true);
            $t->dateTime('effective_from');
            $t->timestamps();
            $t->foreign('activity_type_id')->references('id')->on('activity_types')->cascadeOnDelete();
            $t->index(['activity_type_id','effective_from']);
        });

        Schema::create('customer_multiplier_history', function (Blueprint $t) {
            $t->id();
            $t->uuid('customer_id');
            $t->decimal('multiplier', 10, 4);
            $t->string('currency', 8)->default('IRT');
            $t->dateTime('effective_from');
            $t->timestamps();
            $t->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $t->index(['customer_id','effective_from']);
        });

        Schema::create('project_multiplier_history', function (Blueprint $t) {
            $t->id();
            $t->uuid('project_id');
            $t->uuid('customer_id')->nullable();
            $t->decimal('multiplier', 10, 4);
            $t->boolean('is_billable_default')->default(true);
            $t->dateTime('effective_from');
            $t->timestamps();
            $t->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $t->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $t->index(['project_id','effective_from']);
            $t->index(['customer_id','effective_from']);
        });

        $epoch='1970-01-01 00:00:00';
        $now=now();
        foreach(DB::table('activity_types')->get(['id','base_hourly_rate_minor','currency','is_billable_default']) as $row) {
            DB::table('activity_rate_history')->insert([
                'activity_type_id'=>$row->id,'hourly_rate_minor'=>$row->base_hourly_rate_minor,
                'currency'=>$row->currency,'is_billable_default'=>$row->is_billable_default,
                'effective_from'=>$epoch,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        foreach(DB::table('customers')->get(['id','rate_multiplier','currency']) as $row) {
            DB::table('customer_multiplier_history')->insert([
                'customer_id'=>$row->id,'multiplier'=>$row->rate_multiplier,'currency'=>$row->currency,
                'effective_from'=>$epoch,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
        foreach(DB::table('projects')->get(['id','customer_id','rate_multiplier','is_billable_default']) as $row) {
            DB::table('project_multiplier_history')->insert([
                'project_id'=>$row->id,'customer_id'=>$row->customer_id,'multiplier'=>$row->rate_multiplier,
                'is_billable_default'=>$row->is_billable_default,'effective_from'=>$epoch,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_multiplier_history');
        Schema::dropIfExists('customer_multiplier_history');
        Schema::dropIfExists('activity_rate_history');
    }
};

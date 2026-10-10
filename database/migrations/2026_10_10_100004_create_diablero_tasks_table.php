<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diablero_tasks')) {
            Schema::create('diablero_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_group_id')->unique();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->unsignedBigInteger('diablero_id')->nullable()->index();
                $table->unsignedBigInteger('loading_zone_id')->nullable();
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->string('handover_code', 10);
                $table->decimal('diablero_fee', 24, 2)->default(0);
                $table->decimal('earning', 24, 2)->default(0);
                $table->string('cancel_reason')->nullable();
                $table->timestamp('available_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('collected_at')->nullable();
                $table->timestamp('in_transit_at')->nullable();
                $table->timestamp('handed_over_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('diablero_task_stops')) {
            Schema::create('diablero_task_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diablero_task_id')->index();
                $table->unsignedBigInteger('order_id')->unique();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedInteger('sequence')->default(0);
                $table->string('status', 20)->default('pending');
                $table->string('failure_reason')->nullable();
                $table->timestamp('collected_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('diablero_task_logs')) {
            Schema::create('diablero_task_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diablero_task_id')->index();
                $table->unsignedBigInteger('diablero_task_stop_id')->nullable();
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20);
                $table->string('actor_type', 20);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('note')->nullable();
                $table->decimal('latitude', 16, 10)->nullable();
                $table->decimal('longitude', 16, 10)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('diablero_task_rejections')) {
            Schema::create('diablero_task_rejections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diablero_task_id');
                $table->unsignedBigInteger('diablero_id');
                $table->timestamps();
                $table->unique(['diablero_task_id', 'diablero_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diablero_task_rejections');
        Schema::dropIfExists('diablero_task_logs');
        Schema::dropIfExists('diablero_task_stops');
        Schema::dropIfExists('diablero_tasks');
    }
};

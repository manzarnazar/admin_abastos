<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_groups')) {
            Schema::create('order_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->boolean('is_guest')->default(0);
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->unsignedBigInteger('module_id')->nullable();
                $table->unsignedBigInteger('loading_zone_id')->nullable();
                $table->decimal('order_amount', 24, 2)->default(0);
                $table->decimal('delivery_charge', 24, 2)->default(0);
                $table->decimal('diablero_fee', 24, 2)->default(0);
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_status', 50)->default('unpaid');
                $table->string('otp', 10)->nullable();
                $table->text('callback')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_group_id')) {
                $table->unsignedBigInteger('order_group_id')->nullable()->index();
            }
            if (!Schema::hasColumn('orders', 'diablero_fee')) {
                $table->decimal('diablero_fee', 24, 2)->default(0);
            }
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('order_transactions', 'diablero_fee')) {
                $table->decimal('diablero_fee', 24, 2)->default(0);
            }
            if (!Schema::hasColumn('order_transactions', 'diablero_fee_commission')) {
                $table->decimal('diablero_fee_commission', 24, 2)->default(0);
            }
            if (!Schema::hasColumn('order_transactions', 'diablero_earning')) {
                $table->decimal('diablero_earning', 24, 2)->default(0);
            }
            if (!Schema::hasColumn('order_transactions', 'diablero_id')) {
                $table->unsignedBigInteger('diablero_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn(['diablero_fee', 'diablero_fee_commission', 'diablero_earning', 'diablero_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_group_id', 'diablero_fee']);
        });
        Schema::dropIfExists('order_groups');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diableros')) {
            Schema::create('diableros', function (Blueprint $table) {
                $table->id();
                $table->string('f_name', 100)->nullable();
                $table->string('l_name', 100)->nullable();
                $table->string('phone', 20)->unique();
                $table->string('email', 100)->nullable();
                $table->string('identity_number', 30)->nullable();
                $table->string('identity_type', 50)->nullable();
                $table->json('identity_image')->nullable();
                $table->string('image', 100)->nullable();
                $table->string('password', 100);
                $table->string('auth_token')->nullable()->index();
                $table->string('fcm_token')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->boolean('status')->default(1);
                $table->boolean('active')->default(0);
                $table->enum('application_status', ['approved', 'denied', 'pending'])->default('approved');
                $table->decimal('latitude', 16, 10)->nullable();
                $table->decimal('longitude', 16, 10)->nullable();
                $table->timestamp('location_updated_at')->nullable();
                $table->string('current_language_key', 10)->default('en');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('diablero_wallets')) {
            Schema::create('diablero_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diablero_id')->unique();
                $table->decimal('total_earning', 24, 2)->default(0);
                $table->decimal('total_withdrawn', 24, 2)->default(0);
                $table->decimal('pending_withdraw', 24, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('diablero_wallet_transactions')) {
            Schema::create('diablero_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diablero_id')->index();
                $table->unsignedBigInteger('diablero_task_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('transaction_type', 32);
                $table->decimal('credit', 24, 2)->default(0);
                $table->decimal('debit', 24, 2)->default(0);
                $table->decimal('balance', 24, 2)->default(0);
                $table->string('reference')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'transaction_type'], 'diablero_wallet_tx_order_type_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diablero_wallet_transactions');
        Schema::dropIfExists('diablero_wallets');
        Schema::dropIfExists('diableros');
    }
};

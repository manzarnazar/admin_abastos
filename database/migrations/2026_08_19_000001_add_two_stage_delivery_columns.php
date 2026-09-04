<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->string('role', 32)->default('driver')->after('type');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('requires_diablero')->default(0);
        });

        Schema::table(Schema::hasTable('store_configs') ? 'store_configs' : 'storeConfigs', function (Blueprint $table) {
            $table->boolean('requires_diablero')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('requires_diablero')->default(0);
            $table->unsignedBigInteger('diablero_id')->nullable();
            $table->timestamp('diablero_assigned')->nullable();
            $table->timestamp('diablero_picked_up')->nullable();
            $table->timestamp('handed_to_vehicle')->nullable();
            $table->timestamp('delivery_man_assigned')->nullable();
            $table->timestamp('out_for_delivery')->nullable();
            $table->decimal('handoff_latitude', 16, 10)->nullable();
            $table->decimal('handoff_longitude', 16, 10)->nullable();

            $table->foreign('diablero_id')->references('id')->on('delivery_men')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['diablero_id']);
            $table->dropColumn([
                'requires_diablero',
                'diablero_id',
                'diablero_assigned',
                'diablero_picked_up',
                'handed_to_vehicle',
                'delivery_man_assigned',
                'out_for_delivery',
                'handoff_latitude',
                'handoff_longitude',
            ]);
        });

        Schema::table(Schema::hasTable('store_configs') ? 'store_configs' : 'storeConfigs', function (Blueprint $table) {
            $table->dropColumn('requires_diablero');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('requires_diablero');
        });

        Schema::table('delivery_men', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

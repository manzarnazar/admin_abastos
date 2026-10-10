<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'order_status')) {
            // Normalize two-leg statuses back to single-leg before dropping columns.
            DB::statement("
                UPDATE orders SET order_status = CASE
                    WHEN order_status IN ('diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle') AND delivery_man_id IS NULL AND handover IS NOT NULL THEN 'handover'
                    WHEN order_status IN ('diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle') AND delivery_man_id IS NULL AND processing IS NOT NULL THEN 'processing'
                    WHEN order_status IN ('diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle') AND delivery_man_id IS NULL AND confirmed IS NOT NULL THEN 'confirmed'
                    WHEN order_status IN ('diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle') AND delivery_man_id IS NULL THEN 'pending'
                    WHEN order_status = 'out_for_delivery' AND delivery_man_id IS NOT NULL THEN 'picked_up'
                    WHEN order_status = 'delivery_man_assigned' AND delivery_man_id IS NOT NULL THEN 'accepted'
                    WHEN order_status IN ('diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle', 'delivery_man_assigned', 'out_for_delivery') AND delivery_man_id IS NOT NULL THEN 'accepted'
                    ELSE order_status
                END
                WHERE order_status IN ('diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle', 'delivery_man_assigned', 'out_for_delivery')
            ");

            if (Schema::hasColumn('orders', 'out_for_delivery')) {
                DB::statement("
                    UPDATE orders
                    SET picked_up = COALESCE(picked_up, out_for_delivery)
                    WHERE out_for_delivery IS NOT NULL AND picked_up IS NULL AND delivery_man_id IS NOT NULL
                ");
            }

            if (Schema::hasColumn('orders', 'delivery_man_assigned')) {
                DB::statement("
                    UPDATE orders
                    SET accepted = COALESCE(accepted, delivery_man_assigned)
                    WHERE delivery_man_assigned IS NOT NULL AND accepted IS NULL AND delivery_man_id IS NOT NULL
                ");
            }
        }

        if (Schema::hasColumn('orders', 'diablero_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['diablero_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $cols = [
                'requires_diablero',
                'diablero_id',
                'diablero_assigned',
                'diablero_picked_up',
                'handed_to_vehicle',
                'delivery_man_assigned',
                'out_for_delivery',
                'handoff_latitude',
                'handoff_longitude',
            ];
            $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('orders', $c)));
            if ($existing) {
                $table->dropColumn($existing);
            }
        });

        $storeConfigTable = Schema::hasTable('store_configs') ? 'store_configs' : (Schema::hasTable('storeConfigs') ? 'storeConfigs' : null);
        if ($storeConfigTable && Schema::hasColumn($storeConfigTable, 'requires_diablero')) {
            Schema::table($storeConfigTable, function (Blueprint $table) {
                $table->dropColumn('requires_diablero');
            });
        }

        if (Schema::hasColumn('zones', 'requires_diablero')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropColumn('requires_diablero');
            });
        }

        if (Schema::hasColumn('delivery_men', 'role')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        // Irreversible removal of two-stage delivery feature.
    }
};

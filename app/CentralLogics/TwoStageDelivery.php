<?php

namespace App\CentralLogics;

use App\Models\DeliveryHistory;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\Store;
use App\Models\Zone;

class TwoStageDelivery
{
    public static function shouldApply(?string $orderType, $store, $zone): bool
    {
        if ($orderType !== 'delivery') {
            return false;
        }

        if ($store && (int) ($store->sub_self_delivery ?? $store->self_delivery_system ?? 0) === 1) {
            return false;
        }

        $zoneFlag = (bool) ($zone->requires_diablero ?? false);
        $storeFlag = (bool) ($store?->storeConfig?->requires_diablero ?? false);

        return $zoneFlag || $storeFlag;
    }

    public static function applyToOrder(Order $order, $store, $zone): void
    {
        $order->requires_diablero = self::shouldApply($order->order_type, $store, $zone) ? 1 : 0;
    }

    public static function diableroTopic(int $zoneId): string
    {
        return 'zone_'.$zoneId.'_diablero';
    }

    public static function preserveTwoLegStatus(Order $order, string $requestedStatus): string
    {
        if (! $order->requires_diablero) {
            return $requestedStatus;
        }

        if (in_array($requestedStatus, ['processing', 'handover'], true)
            && in_array($order->order_status, ['diablero_assigned', 'diablero_picked_up', 'handed_to_vehicle', 'delivery_man_assigned', 'out_for_delivery'], true)) {
            return $order->order_status;
        }

        return $requestedStatus;
    }

    public static function isDiablero(?DeliveryMan $dm): bool
    {
        return $dm && ($dm->role ?? 'driver') === 'diablero';
    }

    public static function notifyRole(Order $order, string $role): void
    {
        if (! $order->zone || ! Helpers::getNotificationStatusData('deliveryman', 'deliveryman_order_notification', 'push_notification_status')) {
            return;
        }

        $data = [
            'title' => translate('Order_Notification'),
            'description' => translate('New order alert, confirm to proceed'),
            'order_id' => $order->id,
            'module_id' => $order->module_id,
            'order_type' => $order->order_type,
            'image' => '',
            'type' => 'order_request',
        ];

        if ($role === 'diablero') {
            Helpers::send_push_notif_to_topic($data, self::diableroTopic($order->zone_id), 'order_request');

            return;
        }

        if ($order->dm_vehicle_id) {
            Helpers::send_push_notif_to_topic($data, 'delivery_man_'.$order->zone_id.'_'.$order->dm_vehicle_id, 'order_request');
        }
        Helpers::send_push_notif_to_topic($data, $order->zone->deliveryman_wise_topic, 'order_request');
    }

    public static function lastLocation(int $deliveryManId): ?DeliveryHistory
    {
        return DeliveryHistory::where('delivery_man_id', $deliveryManId)->first();
    }

    public static function hideCustomerDiableroFields(Order $order): Order
    {
        if (! $order->requires_diablero) {
            return $order;
        }

        $driverAssigned = ! empty($order->delivery_man_id) && in_array($order->order_status, [
            'delivery_man_assigned',
            'out_for_delivery',
            'picked_up',
            'delivered',
        ], true);

        if (! $driverAssigned) {
            $order->setRelation('delivery_man', null);
            $order->delivery_man_id = null;
        }

        unset($order->diablero_id, $order->handoff_latitude, $order->handoff_longitude);

        return $order;
    }
}

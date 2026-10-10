<?php

namespace App\Traits;

use App\CentralLogics\DiableroLogic;
use App\CentralLogics\Helpers;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\LoadingZone;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Multi-store checkout for diablero zones. Each store gets a regular order created by
 * PlaceNewOrder::new_place_order; this trait links them to one order group and one diablero task.
 * The using class must also use PlaceNewOrder.
 */
trait PlaceGroupOrder
{
    public function new_place_group_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:cash_on_delivery,digital_payment,wallet',
            'order_type' => 'required|in:delivery',
            'address' => 'required',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
            'dm_tips' => 'nullable|numeric|min:0',
            'guest_id' => $request->user ? 'nullable' : 'required',
            'contact_person_name' => $request->user ? 'nullable' : 'required',
            'contact_person_number' => $request->user ? 'nullable' : 'required',
            'contact_person_email' => $request->user ? 'nullable' : 'required',
            'password' => $request->create_new_user ? ['required', Password::min(8)] : 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if ($request->partial_payment) {
            return $this->groupError('partial_payment', translate('messages.partial_payment_is_not_available_for_multi_store_orders'));
        }
        if ($request->payment_method === 'offline_payment' || $request->boolean('offline_payment')) {
            return $this->groupError('offline_payment', translate('messages.offline_payment_is_not_available_for_multi_store_orders'));
        }

        $zone = $this->resolveDiableroZone($request);
        if (!$zone) {
            return $this->groupError('zone', translate('messages.diablero_service_is_not_available_in_your_area'));
        }
        $loadingZone = LoadingZone::defaultForZone($zone->id);
        $settings = DiableroLogic::settings();

        DB::beginTransaction();
        try {
            $createNewUser = $this->createNewUser($request);
            if (data_get($createNewUser, 'newUser') === true) {
                $request->merge(['user' => data_get($createNewUser, 'user'), 'create_new_user' => 0]);
                $request->is_guest = 0;
            } elseif (data_get($createNewUser, 'status_code') === 403) {
                DB::rollBack();
                return $this->groupError(data_get($createNewUser, 'code'), data_get($createNewUser, 'message'));
            }

            $userId = $request->user ? $request->user->id : $request['guest_id'];
            $isGuest = $request->user ? 0 : 1;

            $storeCarts = $this->groupCartsByStore($userId, $isGuest, $request->header('moduleId'));
            if ($storeCarts->isEmpty()) {
                DB::rollBack();
                return $this->groupError('empty_cart', translate('messages.You_can_not_place_empty_orders'));
            }
            if ($storeCarts->count() > 1 && $request['coupon_code']) {
                DB::rollBack();
                return $this->groupError('coupon', translate('messages.coupons_are_not_available_for_multi_store_orders'));
            }

            $distance = DiableroLogic::distanceKm((float) $loadingZone->latitude, (float) $loadingZone->longitude, (float) $request->latitude, (float) $request->longitude);
            $request->merge(['distance' => $distance]);
            $moduleCharge = $zone->modules()->where('modules.id', $request->header('moduleId'))->first();
            $chargeData = $this->getDeliveryCharge($request, $zone, null, $moduleCharge, null, $request->header('moduleId'));
            $groupDelivery = round((float) data_get($chargeData, 'delivery_charge', 0), 2);
            $storeCount = $storeCarts->count();
            $deliveryShares = DiableroLogic::splitAmount($groupDelivery, $storeCount);
            $feeShares = DiableroLogic::splitAmount($settings['fee'], $storeCount);

            $group = OrderGroup::create([
                'user_id' => $userId,
                'is_guest' => $isGuest,
                'zone_id' => $zone->id,
                'module_id' => $request->header('moduleId'),
                'loading_zone_id' => $loadingZone->id,
                'delivery_charge' => $groupDelivery,
                'diablero_fee' => $settings['fee'],
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'otp' => (string) rand(1000, 9999),
            ]);

            $orderIds = [];
            $index = 0;
            $originalTips = $request->dm_tips;
            $originalCoupon = $request['coupon_code'];
            foreach ($storeCarts as $storeId => $cartIds) {
                if ($request->user) {
                    $request->merge(['user' => $request->user->fresh()]);
                }
                $request->merge([
                    'store_id' => $storeId,
                    'is_buy_now' => 0,
                    'dm_tips' => $index === 0 ? $originalTips : 0,
                    'coupon_code' => $storeCount === 1 ? $originalCoupon : null,
                    'diablero_group' => [
                        'group_id' => $group->id,
                        'cart_ids' => $cartIds,
                        'delivery_share' => $deliveryShares[$index],
                        'diablero_fee_share' => $feeShares[$index],
                        'charge_additional' => $index === 0,
                        'otp' => $group->otp,
                        'distance' => $distance,
                        'vehicle_id' => data_get($chargeData, 'vehicle_id'),
                    ],
                ]);

                $response = $this->new_place_order($request);
                if ($response->getStatusCode() !== 200) {
                    DB::rollBack();
                    return $response;
                }
                $orderIds[] = $response->getData(true)['order_id'];
                $index++;
            }

            $orders = Order::whereIn('id', $orderIds)->get();
            $group->order_amount = round($orders->sum('order_amount'), 2);
            $group->save();

            DiableroLogic::createTaskForGroup($group);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            return $this->groupError('order', translate('messages.failed_to_place_order'));
        }

        $request->merge(['diablero_group' => null]);
        foreach ($orders as $order) {
            $this->sentOrderPlaceNotification($request, $order, $order->store);
        }

        $first = $orders->sortBy('id')->first();
        return response()->json([
            'message' => translate('messages.order_placed_successfully'),
            'order_id' => $first->id,
            'order_group_id' => $group->id,
            'order_ids' => $orders->pluck('id')->sort()->values(),
            'total_ammount' => $group->order_amount,
            'status' => $first->order_status,
            'created_at' => $first->created_at,
            'user_id' => (int) $first->user_id,
        ], 200);
    }

    public function diablero_checkout_summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone = $this->resolveDiableroZone($request);
        $settings = DiableroLogic::settings();
        if (!$zone) {
            return response()->json(['diablero_status' => 0, 'diablero_fee' => 0, 'diablero_fee_name' => $settings['fee_name']]);
        }
        $loadingZone = LoadingZone::defaultForZone($zone->id);
        $distance = DiableroLogic::distanceKm((float) $loadingZone->latitude, (float) $loadingZone->longitude, (float) $request->latitude, (float) $request->longitude);
        $request->merge(['distance' => $distance, 'order_type' => 'delivery']);
        $moduleCharge = $zone->modules()->where('modules.id', $request->header('moduleId'))->first();
        $chargeData = $this->getDeliveryCharge($request, $zone, null, $moduleCharge, null, $request->header('moduleId'));

        return response()->json([
            'diablero_status' => 1,
            'zone_id' => $zone->id,
            'diablero_fee' => $settings['fee'],
            'diablero_fee_name' => $settings['fee_name'],
            'delivery_charge' => round((float) data_get($chargeData, 'delivery_charge', 0), 2),
            'distance' => $distance,
            'loading_zone' => [
                'id' => $loadingZone->id,
                'name' => $loadingZone->name,
                'address' => $loadingZone->address,
                'latitude' => (string) $loadingZone->latitude,
                'longitude' => (string) $loadingZone->longitude,
            ],
        ]);
    }

    /**
     * Cart based delivery orders in diablero zones always go through the group flow, so older app
     * builds that still call the single-store endpoint get a diablero as well.
     */
    protected function requiresDiablero(Request $request): bool
    {
        return $request->order_type === 'delivery'
            && !$request->boolean('is_buy_now')
            && !$request->hasFile('order_attachment')
            && $this->resolveDiableroZone($request) !== null;
    }

    private function resolveDiableroZone(Request $request): ?Zone
    {
        if (!DiableroLogic::settings()['status'] || !$request->latitude || !$request->longitude) {
            return null;
        }
        $zone = Zone::where('status', 1)->where('diablero_status', 1)
            ->whereContains('coordinates', new Point((float) $request->latitude, (float) $request->longitude, POINT_SRID))
            ->first();

        return $zone && DiableroLogic::isEnabledForZone($zone) ? $zone : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<int>> store_id => cart ids
     */
    private function groupCartsByStore($userId, int $isGuest, $moduleId)
    {
        $carts = Cart::where('user_id', $userId)->where('is_guest', $isGuest)->where('module_id', $moduleId)->get();
        $grouped = [];
        foreach ($carts as $cart) {
            $isCampaign = in_array($cart->item_type, ['App\Models\ItemCampaign', 'AppModelsItemCampaign'], true);
            $storeId = $isCampaign
                ? ItemCampaign::where('id', $cart->item_id)->value('store_id')
                : Item::where('id', $cart->item_id)->value('store_id');
            if ($storeId) {
                $grouped[$storeId][] = $cart->id;
            }
        }
        ksort($grouped);

        return collect($grouped);
    }

    private function groupError(?string $code, ?string $message, int $status = 403)
    {
        return response()->json(['errors' => [['code' => $code, 'message' => $message]]], $status);
    }
}

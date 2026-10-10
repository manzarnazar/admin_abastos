<?php

namespace Tests\Feature\Diablero;

use App\Models\DiableroTask;

class GroupCheckoutTest extends DiableroTestCase
{
    public function test_multi_store_cart_creates_linked_orders_and_one_task()
    {
        $group = $this->placeTwoStoreGroup();
        $orders = $this->groupOrders($group);

        $this->assertCount(2, $orders);
        $this->assertEqualsCanonicalizing([$this->storeA->id, $this->storeB->id], $orders->pluck('store_id')->all());
        $this->assertEquals(30.0, round($orders->sum('diablero_fee'), 2));
        $this->assertEquals(round($group->delivery_charge, 2), round($orders->sum('original_delivery_charge'), 2));
        $this->assertEquals(round($orders->sum('order_amount'), 2), round((float) $group->order_amount, 2));
        $this->assertCount(1, $orders->pluck('otp')->unique());
        $this->assertEquals($group->otp, $orders->first()->otp);

        $task = $this->task($group);
        $this->assertEquals(DiableroTask::PENDING, $task->status);
        $this->assertEquals(24.0, (float) $task->earning);
        $this->assertCount(2, $task->stops);
        $this->assertEquals($this->loadingZone->id, $task->loading_zone_id);
    }

    public function test_checkout_summary_reports_fee_in_enabled_zone()
    {
        $this->getJson('/api/v1/customer/order/diablero-summary?latitude=' . self::CUSTOMER_LAT . '&longitude=' . self::CUSTOMER_LNG, $this->customerHeaders())
            ->assertOk()
            ->assertJsonPath('diablero_status', 1)
            ->assertJsonPath('diablero_fee', 30);
    }

    public function test_group_checkout_rejected_when_zone_not_enabled()
    {
        $this->zone->forceFill(['diablero_status' => 0])->save();
        $this->addToCart($this->itemA);
        $this->placeGroup()->assertStatus(403)->assertJsonPath('errors.0.code', 'zone');
    }

    public function test_group_checkout_rejected_when_service_disabled_globally()
    {
        $this->setting('diablero_service_status', 0);
        $this->addToCart($this->itemA);
        $this->placeGroup()->assertStatus(403)->assertJsonPath('errors.0.code', 'zone');
    }

    public function test_partial_payment_is_rejected()
    {
        $this->addToCart($this->itemA);
        $this->placeGroup(['partial_payment' => 1])->assertStatus(403)->assertJsonPath('errors.0.code', 'partial_payment');
    }

    private function placeLegacy()
    {
        return $this->postJson('/api/v1/customer/order/place', [
            'payment_method' => 'wallet',
            'order_type' => 'delivery',
            'store_id' => $this->storeA->id,
            'address' => 'Customer street 1',
            'latitude' => self::CUSTOMER_LAT,
            'longitude' => self::CUSTOMER_LNG,
            'contact_person_name' => 'Test',
            'contact_person_number' => '+524625090577',
            'address_type' => 'home',
            'distance' => 1,
            'is_buy_now' => 0,
        ], $this->customerHeaders());
    }

    public function test_legacy_place_endpoint_uses_diablero_in_enabled_zone()
    {
        $this->addToCart($this->itemA);
        $response = $this->placeLegacy()->assertOk();

        $order = \App\Models\Order::withoutGlobalScopes()->findOrFail($response->json('order_id'));
        $this->assertNotNull($order->order_group_id);
        $this->assertEquals(30.0, (float) $order->diablero_fee);
        $this->assertNotNull(DiableroTask::where('order_group_id', $order->order_group_id)->first());
    }

    public function test_legacy_place_endpoint_is_unchanged_outside_diablero_zones()
    {
        $this->zone->forceFill(['diablero_status' => 0])->save();
        $this->addToCart($this->itemA);
        $response = $this->placeLegacy()->assertOk();

        $order = \App\Models\Order::withoutGlobalScopes()->findOrFail($response->json('order_id'));
        $this->assertNull($order->order_group_id);
        $this->assertEquals(0.0, (float) $order->diablero_fee);
        $this->assertEquals(0, DiableroTask::count());
    }

    public function test_wallet_is_charged_once_for_whole_group()
    {
        $before = (float) $this->customer->fresh()->wallet_balance;
        $group = $this->placeTwoStoreGroup();
        $after = (float) $this->customer->fresh()->wallet_balance;

        $this->assertEquals(round((float) $group->order_amount, 2), round($before - $after, 2));
    }

    public function test_cod_group_checkout()
    {
        $this->addToCart($this->itemA);
        $this->addToCart($this->itemB);
        $response = $this->placeGroup(['payment_method' => 'cash_on_delivery'])->assertOk();
        $group = \App\Models\OrderGroup::findOrFail($response->json('order_group_id'));
        $this->assertEquals('unpaid', $group->payment_status);
        $this->assertEquals('cash_on_delivery', $group->payment_method);
        $this->assertNotNull($this->task($group));
    }

    public function test_offline_payment_is_rejected()
    {
        $this->addToCart($this->itemA);
        $this->placeGroup(['payment_method' => 'offline_payment'])->assertStatus(403);
        $this->placeGroup(['payment_method' => 'wallet', 'offline_payment' => 1])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'offline_payment');
    }

    public function test_digital_payment_hooks_place_and_fail_the_group()
    {
        $this->addToCart($this->itemA);
        $this->addToCart($this->itemB);
        $response = $this->placeGroup(['payment_method' => 'digital_payment'])->assertOk();
        $group = \App\Models\OrderGroup::findOrFail($response->json('order_group_id'));
        $this->assertEquals('unpaid', $group->payment_status);

        order_group_place((object) ['attribute_id' => $group->id, 'payment_method' => 'stripe']);
        $this->assertEquals('paid', $group->fresh()->payment_status);
        $this->assertEquals(['paid'], $this->groupOrders($group)->pluck('payment_status')->unique()->values()->all());

        $this->addToCart($this->itemA);
        $this->addToCart($this->itemB);
        $failed = $this->placeGroup(['payment_method' => 'digital_payment'])->assertOk();
        $failedGroup = \App\Models\OrderGroup::findOrFail($failed->json('order_group_id'));
        order_group_failed((object) ['attribute_id' => $failedGroup->id, 'payment_method' => 'stripe']);
        $this->assertEquals('failed', $failedGroup->fresh()->payment_status);
        $this->assertEquals(['failed'], $this->groupOrders($failedGroup)->pluck('order_status')->unique()->values()->all());
        $this->assertEquals(\App\Models\DiableroTask::CANCELED, $this->task($failedGroup)->status);
    }
}

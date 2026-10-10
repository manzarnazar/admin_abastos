<?php

namespace Tests\Feature\Diablero;

use App\Models\DiableroTask;
use App\Models\DiableroWallet;
use App\Models\Order;
use App\Models\OrderTransaction;

class HandoverAndSettlementTest extends DiableroTestCase
{
    public function test_deliveryman_sees_group_once_and_accepts_all_orders()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $dm = $this->makeDeliveryMan();
        $orders = $this->groupOrders($group);

        $latest = collect($this->getJson('/api/v1/delivery-man/latest-orders?token=' . $dm->auth_token)->assertOk()->json());
        $groupRows = $latest->where('order_group_id', $group->id);
        $this->assertCount(1, $groupRows);
        $this->assertEquals($orders->first()->id, $groupRows->first()['id']);
        $this->assertCount(2, $groupRows->first()['diablero_info']['orders']);

        $this->putJson('/api/v1/delivery-man/accept-order', ['token' => $dm->auth_token, 'order_id' => $orders->first()->id])->assertOk();
        $this->assertEquals([$dm->id], $this->groupOrders($group)->pluck('delivery_man_id')->unique()->values()->all());
        $this->assertEquals(2, $dm->fresh()->current_orders);

        $this->putJson('/api/v1/delivery-man/accept-order', ['token' => $dm->auth_token, 'order_id' => $orders->last()->id])->assertStatus(404);

        $current = collect($this->getJson('/api/v1/delivery-man/current-orders?token=' . $dm->auth_token)->assertOk()->json());
        $this->assertCount(1, $current->where('order_group_id', $group->id));
    }

    public function test_deliveryman_cannot_pick_up_grouped_order_directly()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $dm = $this->makeDeliveryMan();
        $order = $this->groupOrders($group)->first();
        $this->putJson('/api/v1/delivery-man/accept-order', ['token' => $dm->auth_token, 'order_id' => $order->id])->assertOk();

        $this->putJson('/api/v1/delivery-man/update-order-status', ['token' => $dm->auth_token, 'order_id' => $order->id, 'status' => 'picked_up'])
            ->assertStatus(403)->assertJsonPath('errors.0.code', 'diablero');
        $this->putJson('/api/v1/delivery-man/update-order-status', ['token' => $dm->auth_token, 'order_id' => $order->id, 'status' => 'delivered', 'otp' => $group->otp])
            ->assertStatus(403);
    }

    public function test_handover_requires_assignment_transit_and_correct_code()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $dm = $this->makeDeliveryMan();
        $first = $this->groupOrders($group)->first();
        $url = '/api/v1/delivery-man/confirm-diablero-handover';

        $this->postJson($url, ['token' => $dm->auth_token, 'order_group_id' => $group->id, 'code' => '0000'])->assertStatus(403)->assertJsonPath('errors.0.code', 'not_assigned');

        $this->putJson('/api/v1/delivery-man/accept-order', ['token' => $dm->auth_token, 'order_id' => $first->id])->assertOk();
        $diablero = $this->makeDiablero();
        $task = $this->task($group);
        $h = $this->diableroHeaders($diablero);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk();
        foreach ($task->stops as $stop) {
            $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stop->id}/collect", [], $h)->assertOk();
        }
        $code = $task->fresh()->handover_code;

        $this->postJson($url, ['token' => $dm->auth_token, 'order_group_id' => $group->id, 'code' => $code])->assertStatus(403)->assertJsonPath('errors.0.code', 'status');

        $this->postJson("/api/v1/diablero/tasks/{$task->id}/status", ['status' => 'in_transit'], $h)->assertOk();
        $wrong = $code === '0000' ? '1111' : '0000';
        $this->postJson($url, ['token' => $dm->auth_token, 'order_group_id' => $group->id, 'code' => $wrong])->assertStatus(406);
        $this->assertEquals(DiableroTask::IN_TRANSIT, $task->fresh()->status);

        $this->postJson($url, ['token' => $dm->auth_token, 'order_group_id' => $group->id, 'code' => $code])->assertOk();
        $task->refresh();
        $this->assertEquals(DiableroTask::HANDED_OVER, $task->status);
        $this->assertEquals($dm->id, $task->delivery_man_id);
        $this->assertEquals(['picked_up'], $this->groupOrders($group)->pluck('order_status')->unique()->values()->all());

        $this->postJson($url, ['token' => $dm->auth_token, 'order_group_id' => $group->id, 'code' => $code])->assertStatus(403);
    }

    public function test_delivery_cascades_to_group_and_settles_diablero_fee()
    {
        $group = $this->placeTwoStoreGroup();
        $dm = $this->makeDeliveryMan();
        $orders = $this->groupOrders($group);
        $this->putJson('/api/v1/delivery-man/accept-order', ['token' => $dm->auth_token, 'order_id' => $orders->first()->id])->assertOk();
        $diablero = $this->driveToInTransit($group);
        $task = $this->task($group);

        $this->postJson('/api/v1/delivery-man/confirm-diablero-handover', ['token' => $dm->auth_token, 'order_group_id' => $group->id, 'code' => $task->fresh()->handover_code])->assertOk();

        $this->putJson('/api/v1/delivery-man/update-order-status', ['token' => $dm->auth_token, 'order_id' => $orders->first()->id, 'status' => 'delivered', 'otp' => 'bad'])->assertStatus(406);
        $this->putJson('/api/v1/delivery-man/update-order-status', ['token' => $dm->auth_token, 'order_id' => $orders->first()->id, 'status' => 'delivered', 'otp' => $group->otp])->assertOk();

        $orders = $this->groupOrders($group);
        $this->assertEquals(['delivered'], $orders->pluck('order_status')->unique()->values()->all());
        $this->assertEquals(0, $dm->fresh()->current_orders);

        $transactions = OrderTransaction::whereIn('order_id', $orders->pluck('id'))->get();
        $this->assertCount(2, $transactions);
        $this->assertEquals(30.0, round($transactions->sum('diablero_fee'), 2));
        $this->assertEquals(24.0, round($transactions->sum('diablero_earning'), 2));
        $this->assertEquals(6.0, round($transactions->sum('diablero_fee_commission'), 2));
        $this->assertEquals([$diablero->id], $transactions->pluck('diablero_id')->unique()->values()->all());

        foreach ($orders as $order) {
            $transaction = $transactions->firstWhere('order_id', $order->id);
            $productAndTax = $order->order_amount - $order->delivery_charge - $order->diablero_fee - $order->dm_tips - $order->additional_charge;
            $this->assertLessThanOrEqual(round($productAndTax, 2) + 0.01, round((float) $transaction->store_amount, 2));
        }

        $wallet = DiableroWallet::where('diablero_id', $diablero->id)->first();
        $this->assertEquals(24.0, round((float) $wallet->total_earning, 2));
        $this->assertEquals(2, $diablero->walletTransactions()->where('transaction_type', 'task_earning')->count());

        $this->getJson('/api/v1/diablero/earnings', $this->diableroHeaders($diablero))->assertOk()->assertJsonPath('total_earning', 24);
    }

    public function test_wallet_credit_is_idempotent()
    {
        $group = $this->placeTwoStoreGroup();
        $diablero = $this->makeDiablero();
        $task = $this->task($group);
        $task->forceFill(['status' => DiableroTask::HANDED_OVER, 'diablero_id' => $diablero->id])->save();
        $order = $this->groupOrders($group)->first();
        $split = \App\CentralLogics\DiableroLogic::settleOrder($order);

        \App\CentralLogics\DiableroLogic::creditDiablero($order, $split);
        \App\CentralLogics\DiableroLogic::creditDiablero($order, $split);

        $this->assertEquals(1, $diablero->walletTransactions()->count());
        $this->assertEquals(round($split['earning'], 2), round((float) DiableroWallet::where('diablero_id', $diablero->id)->value('total_earning'), 2));
    }

    public function test_fee_goes_entirely_to_admin_when_task_not_handed_over()
    {
        $group = $this->placeTwoStoreGroup();
        $order = $this->groupOrders($group)->first();
        $split = \App\CentralLogics\DiableroLogic::settleOrder($order);
        $this->assertEquals((float) $order->diablero_fee, $split['commission']);
        $this->assertEquals(0.0, $split['earning']);
        $this->assertNull($split['diablero_id']);
    }

    public function test_payout_cannot_exceed_balance()
    {
        $diablero = $this->makeDiablero();
        DiableroWallet::forceCreate(['diablero_id' => $diablero->id, 'total_earning' => 50]);

        [$ok] = \App\CentralLogics\DiableroLogic::recordPayout($diablero, 60, null, null);
        $this->assertFalse($ok);
        [$ok] = \App\CentralLogics\DiableroLogic::recordPayout($diablero, 50, 'bank', null);
        $this->assertTrue($ok);
        $this->assertEquals(0.0, DiableroWallet::where('diablero_id', $diablero->id)->first()->balance);
    }

    public function test_legacy_orders_are_unaffected()
    {
        $order = Order::withoutGlobalScopes()->whereNull('order_group_id')->first();
        if (!$order) {
            $this->markTestSkipped('No legacy order in the local database.');
        }
        $this->assertNull(\App\CentralLogics\DiableroLogic::blockedStatusChange($order, 'picked_up', 'vendor'));
        $this->assertNull(\App\CentralLogics\DiableroLogic::orderSummary($order));
        $split = \App\CentralLogics\DiableroLogic::settleOrder($order);
        $this->assertEquals(0.0, $split['earning']);
        $this->assertEquals((float) $order->diablero_fee, $split['commission']);
    }
}

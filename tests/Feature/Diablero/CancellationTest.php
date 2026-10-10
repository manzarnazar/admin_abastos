<?php

namespace Tests\Feature\Diablero;

use App\CentralLogics\DiableroLogic;
use App\Models\DiableroTask;
use App\Models\DiableroTaskStop;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Support\Str;

class CancellationTest extends DiableroTestCase
{
    public function test_customer_cancelling_one_order_keeps_task_for_the_rest()
    {
        $group = $this->placeTwoStoreGroup();
        [$first, $second] = $this->groupOrders($group)->all();
        $this->setOrder($first, ['order_status' => 'pending', 'confirmed' => null]);

        $this->putJson('/api/v1/customer/order/cancel', ['order_id' => $first->id, 'reason' => 'Changed mind', '_method' => 'put'], $this->customerHeaders())->assertOk();
        $this->assertEquals('canceled', $first->fresh()->order_status);

        $task = $this->task($group);
        $this->assertEquals(DiableroTaskStop::CANCELED, $task->stops->firstWhere('order_id', $first->id)->status);
        $this->assertEquals(DiableroTask::PENDING, $task->status);

        $second->forceFill(['order_status' => 'handover', 'handover' => now()])->save();
        DiableroLogic::syncOrder($second->fresh());
        $this->assertEquals(DiableroTask::AVAILABLE, $task->fresh()->status);
    }

    public function test_cancelling_every_order_cancels_the_task()
    {
        $group = $this->placeTwoStoreGroup();
        foreach ($this->groupOrders($group) as $order) {
            $this->setOrder($order, ['order_status' => 'pending', 'confirmed' => null]);
            $this->putJson('/api/v1/customer/order/cancel', ['order_id' => $order->id, 'reason' => 'No'], $this->customerHeaders())->assertOk();
        }
        $this->assertEquals(DiableroTask::CANCELED, $this->task($group)->status);
    }

    public function test_cancellation_after_acceptance_notifies_and_reevaluates()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $h = $this->diableroHeaders($this->makeDiablero());
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk();
        [$stopA, $stopB] = $task->stops->all();
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stopA->id}/collect", [], $h)->assertOk();

        $orderB = Order::withoutGlobalScopes()->find($stopB->order_id);
        DiableroLogic::cancelOrder($orderB, 'admin cancel');
        DiableroLogic::syncOrder($orderB->fresh(), DiableroLogic::ACTOR_ADMIN, 1);

        $this->assertEquals(DiableroTaskStop::CANCELED, $stopB->fresh()->status);
        $this->assertEquals(DiableroTask::COLLECTED, $task->fresh()->status);
    }

    public function test_vendor_cannot_mark_grouped_order_picked_up_or_delivered()
    {
        $group = $this->placeTwoStoreGroup();
        $order = $this->groupOrders($group)->first();
        $vendor = Vendor::findOrFail($order->store->vendor_id);
        $vendor->forceFill(['auth_token' => Str::random(60)])->save();
        $headers = ['Authorization' => 'Bearer ' . $vendor->auth_token, 'vendorType' => 'owner'];

        $this->putJson('/api/v1/vendor/update-order-status', ['order_id' => $order->id, 'status' => 'picked_up'], $headers)
            ->assertStatus(403);
        $this->putJson('/api/v1/vendor/update-order-status', ['order_id' => $order->id, 'status' => 'delivered', 'otp' => $group->otp], $headers)
            ->assertStatus(403);
        $this->assertNotEquals('delivered', $order->fresh()->order_status);
        $this->assertNotEquals('picked_up', $order->fresh()->order_status);
    }

    public function test_vendor_handover_through_api_makes_task_available()
    {
        $group = $this->placeTwoStoreGroup();
        $orders = $this->groupOrders($group);
        $orders->first()->forceFill(['order_status' => 'handover', 'handover' => now()])->save();

        $last = $orders->last();
        $last->forceFill(['order_status' => 'processing', 'processing' => now(), 'confirmed' => now()])->save();
        $vendor = Vendor::findOrFail($last->store->vendor_id);
        $vendor->forceFill(['auth_token' => Str::random(60)])->save();
        $headers = ['Authorization' => 'Bearer ' . $vendor->auth_token, 'vendorType' => 'owner'];

        $this->putJson('/api/v1/vendor/update-order-status', ['order_id' => $last->id, 'status' => 'handover'], $headers)->assertOk();
        $this->assertEquals(DiableroTask::AVAILABLE, $this->task($group)->status);
    }

    public function test_admin_can_cancel_task_and_orders()
    {
        $group = $this->placeTwoStoreGroup();
        $task = $this->task($group);
        $this->assertTrue(DiableroLogic::cancelTask($task, 'ops', DiableroLogic::ACTOR_ADMIN, 1));
        $this->assertEquals(DiableroTask::CANCELED, $task->fresh()->status);
        $this->assertFalse(DiableroLogic::cancelTask($task->fresh(), 'again', DiableroLogic::ACTOR_ADMIN, 1));
    }

    public function test_unassign_returns_task_to_pool_and_blocks_same_diablero()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $diablero = $this->makeDiablero();
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($diablero))->assertOk();

        $this->assertTrue(DiableroLogic::unassign($task->fresh(), DiableroLogic::ACTOR_ADMIN, 1));
        $task->refresh();
        $this->assertEquals(DiableroTask::AVAILABLE, $task->status);
        $this->assertNull($task->diablero_id);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($diablero))->assertStatus(403);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($this->makeDiablero()))->assertOk();
    }
}

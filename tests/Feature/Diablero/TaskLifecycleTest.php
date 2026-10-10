<?php

namespace Tests\Feature\Diablero;

use App\CentralLogics\DiableroLogic;
use App\Models\DiableroTask;
use App\Models\DiableroTaskStop;
use App\Models\Order;

class TaskLifecycleTest extends DiableroTestCase
{
    public function test_task_becomes_available_only_when_every_store_is_ready()
    {
        $group = $this->placeTwoStoreGroup();
        [$first, $second] = $this->groupOrders($group)->all();

        $first->forceFill(['order_status' => 'handover', 'handover' => now()])->save();
        DiableroLogic::syncOrder($first->fresh());
        $this->assertEquals(DiableroTask::PENDING, $this->task($group)->status);

        $second->forceFill(['order_status' => 'handover', 'handover' => now()])->save();
        DiableroLogic::syncOrder($second->fresh());
        $this->assertEquals(DiableroTask::AVAILABLE, $this->task($group)->status);
    }

    public function test_available_task_is_listed_for_zone_diableros_only()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);

        $local = $this->makeDiablero();
        $ids = collect($this->getJson('/api/v1/diablero/tasks/available', $this->diableroHeaders($local))->assertOk()->json('tasks'))->pluck('id');
        $this->assertTrue($ids->contains($task->id));

        $other = $this->makeDiablero(['zone_id' => 5]);
        $ids = collect($this->getJson('/api/v1/diablero/tasks/available', $this->diableroHeaders($other))->assertOk()->json('tasks'))->pluck('id');
        $this->assertFalse($ids->contains($task->id));
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($other))->assertStatus(403)->assertJsonPath('errors.0.code', 'zone');
    }

    public function test_second_accept_is_rejected()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $a = $this->makeDiablero();
        $b = $this->makeDiablero();

        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($a))->assertOk();
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($b))
            ->assertStatus(409)->assertJsonPath('errors.0.code', 'already_accepted');
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($a))->assertStatus(409);

        $task->refresh();
        $this->assertEquals($a->id, $task->diablero_id);
        $this->assertEquals(1, $task->logs()->where('to_status', DiableroTask::ACCEPTED)->count());
    }

    public function test_pending_task_cannot_be_accepted()
    {
        $group = $this->placeTwoStoreGroup();
        $task = $this->task($group);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($this->makeDiablero()))->assertStatus(409);
        $this->assertNull($task->fresh()->diablero_id);
    }

    public function test_offline_or_rejecting_diablero_cannot_accept()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);

        $offline = $this->makeDiablero(['active' => 0]);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $this->diableroHeaders($offline))->assertStatus(403)->assertJsonPath('errors.0.code', 'active_status');

        $rejecting = $this->makeDiablero();
        $h = $this->diableroHeaders($rejecting);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/reject", [], $h)->assertOk();
        $ids = collect($this->getJson('/api/v1/diablero/tasks/available', $h)->json('tasks'))->pluck('id');
        $this->assertFalse($ids->contains($task->id));
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertStatus(403)->assertJsonPath('errors.0.code', 'rejected');
    }

    public function test_diablero_cannot_exceed_max_ongoing_tasks()
    {
        $diablero = $this->makeDiablero();
        for ($i = 0; $i < DiableroLogic::MAX_ONGOING_TASKS; $i++) {
            $group = $this->placeTwoStoreGroup();
            $this->markAllReady($group);
            $this->postJson("/api/v1/diablero/tasks/{$this->task($group)->id}/accept", [], $this->diableroHeaders($diablero))->assertOk();
        }
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $this->postJson("/api/v1/diablero/tasks/{$this->task($group)->id}/accept", [], $this->diableroHeaders($diablero))
            ->assertStatus(403)->assertJsonPath('errors.0.code', 'max_tasks');
    }

    public function test_full_collection_flow_and_handover_code_visibility()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $diablero = $this->makeDiablero();
        $h = $this->diableroHeaders($diablero);

        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk()->assertJsonMissingPath('task.handover_code');
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/status", ['status' => 'in_transit'], $h)->assertStatus(403);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/status", ['status' => 'arrived'], $h)->assertOk();

        $stops = $task->stops;
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stops[0]->id}/collect", [], $h)->assertOk();
        $this->assertEquals(DiableroTask::ARRIVED, $task->fresh()->status);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stops[0]->id}/collect", [], $h)->assertStatus(403);

        $res = $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stops[1]->id}/collect", [], $h)->assertOk();
        $this->assertEquals(DiableroTask::COLLECTED, $task->fresh()->status);
        $this->assertEquals($task->fresh()->handover_code, $res->json('task.handover_code'));

        $other = $this->makeDiablero();
        $this->getJson("/api/v1/diablero/tasks/{$task->id}", $this->diableroHeaders($other))->assertStatus(404);

        $this->postJson("/api/v1/diablero/tasks/{$task->id}/status", ['status' => 'in_transit'], $h)->assertOk();
        $this->assertEquals(DiableroTask::IN_TRANSIT, $task->fresh()->status);
        $this->assertGreaterThanOrEqual(5, $task->logs()->count());
    }

    public function test_collect_starts_arrival_automatically()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $h = $this->diableroHeaders($this->makeDiablero());
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk();
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$task->stops[0]->id}/collect", [], $h)->assertOk();
        $this->assertEquals(DiableroTask::ARRIVED, $task->fresh()->status);
    }

    public function test_failed_pickup_cancels_that_order_and_task_continues()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $h = $this->diableroHeaders($this->makeDiablero());
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk();
        [$stopA, $stopB] = $task->stops->all();

        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stopA->id}/fail", [], $h)->assertStatus(403);
        $failedOrder = Order::withoutGlobalScopes()->find($stopA->order_id);
        $walletBefore = (float) $this->customer->fresh()->wallet_balance;
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stopA->id}/fail", ['reason' => 'Out of stock'], $h)->assertOk();
        $this->assertEquals(DiableroTaskStop::FAILED, $stopA->fresh()->status);
        $this->assertEquals('canceled', $failedOrder->fresh()->order_status);
        $this->assertGreaterThanOrEqual($walletBefore, (float) $this->customer->fresh()->wallet_balance);

        $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stopB->id}/collect", [], $h)->assertOk();
        $this->assertEquals(DiableroTask::COLLECTED, $task->fresh()->status);
    }

    public function test_all_pickups_failed_marks_task_failed()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $h = $this->diableroHeaders($this->makeDiablero());
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk();
        foreach ($task->stops as $stop) {
            $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stop->id}/fail", ['reason' => 'Closed'], $h)->assertOk();
        }
        $this->assertEquals(DiableroTask::FAILED, $task->fresh()->status);
        $this->assertEquals(['canceled'], $this->groupOrders($group)->pluck('order_status')->unique()->values()->all());
    }

    public function test_invalid_transitions_are_refused()
    {
        $group = $this->placeTwoStoreGroup();
        $task = $this->task($group);
        $this->assertFalse(DiableroLogic::transition($task, DiableroTask::HANDED_OVER, DiableroLogic::ACTOR_SYSTEM));
        $this->assertFalse(DiableroLogic::transition($task, DiableroTask::IN_TRANSIT, DiableroLogic::ACTOR_SYSTEM));
        $this->assertEquals(DiableroTask::PENDING, $task->fresh()->status);
    }
}

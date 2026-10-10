<?php

namespace Tests\Feature\Diablero;

use App\Models\Admin;
use App\Models\DiableroTask;
use App\Models\DiableroWallet;

class AdminPanelTest extends DiableroTestCase
{
    private function admin()
    {
        $admin = Admin::where('role_id', 1)->firstOrFail();

        return $this->actingAs($admin, 'admin')->withSession(['login_remember_token' => $admin->login_remember_token]);
    }

    public function test_diablero_pages_render()
    {
        $group = $this->placeTwoStoreGroup();
        $task = $this->task($group);
        $diablero = $this->makeDiablero();
        $order = $this->groupOrders($group)->first();

        foreach ([
            '/admin/diablero/list',
            '/admin/diablero/add',
            "/admin/diablero/edit/{$diablero->id}",
            "/admin/diablero/view/{$diablero->id}",
            "/admin/diablero/view/{$diablero->id}?tab=transactions",
            '/admin/diablero/loading-zone',
            '/admin/diablero/task/list',
            '/admin/diablero/task/list/pending',
            "/admin/diablero/task/view/{$task->id}",
            '/admin/business-settings/business-setup',
            '/admin/business-settings/zone/module-setup/' . self::ZONE_ID,
        ] as $uri) {
            $this->admin()->get($uri)->assertOk();
        }

        $this->admin()->get("/admin/order/details/{$order->id}")->assertOk()
            ->assertSee(route('admin.diablero.task.view', $task->id))
            ->assertSee('Diablero Fee');
    }

    public function test_admin_can_assign_and_record_payout()
    {
        $group = $this->placeTwoStoreGroup();
        $this->markAllReady($group);
        $task = $this->task($group);
        $diablero = $this->makeDiablero();

        $this->admin()->post("/admin/diablero/task/assign/{$task->id}", ['diablero_id' => $diablero->id])->assertRedirect();
        $task->refresh();
        $this->assertEquals(DiableroTask::ACCEPTED, $task->status);
        $this->assertEquals($diablero->id, $task->diablero_id);

        DiableroWallet::forceCreate(['diablero_id' => $diablero->id, 'total_earning' => 100]);
        $this->admin()->post("/admin/diablero/payout/{$diablero->id}", ['amount' => 40, 'reference' => 'TRX-1'])->assertRedirect();
        $this->assertEquals(60.0, DiableroWallet::where('diablero_id', $diablero->id)->first()->balance);
    }

    public function test_admin_cannot_mark_grouped_order_picked_up_while_task_open()
    {
        $group = $this->placeTwoStoreGroup();
        $order = $this->groupOrders($group)->first();

        $this->admin()->get("/admin/order/status?id={$order->id}&order_status=picked_up")->assertRedirect();
        $this->assertNotEquals('picked_up', $order->fresh()->order_status);
    }
}

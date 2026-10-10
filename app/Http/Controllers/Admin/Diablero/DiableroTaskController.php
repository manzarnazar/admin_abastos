<?php

namespace App\Http\Controllers\Admin\Diablero;

use App\CentralLogics\DiableroLogic;
use App\Http\Controllers\Controller;
use App\Models\Diablero;
use App\Models\DiableroTask;
use App\Models\Order;
use App\Models\Zone;
use App\Scopes\ZoneScope;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class DiableroTaskController extends Controller
{
    public function index(Request $request, $status = 'all')
    {
        $statuses = array_merge(['all', 'ongoing'], array_keys(DiableroLogic::TRANSITIONS));
        $status = in_array($status, $statuses, true) ? $status : 'all';

        $tasks = DiableroTask::with(['diablero', 'loadingZone', 'zone'])->withCount('stops')
            ->when($status === 'ongoing', fn ($q) => $q->whereIn('status', DiableroTask::ONGOING_STATUSES))
            ->when(!in_array($status, ['all', 'ongoing'], true), fn ($q) => $q->where('status', $status))
            ->when($request->zone_id, fn ($q) => $q->where('zone_id', $request->zone_id))
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q) use ($search) {
                    $q->where('id', $search)->orWhere('order_group_id', $search)
                        ->orWhereHas('stops', fn ($s) => $s->where('order_id', $search));
                });
            })
            ->latest()->paginate(config('default_pagination'))->withQueryString();

        $counts = DiableroTask::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $zones = Zone::orderBy('name')->get(['id', 'name']);

        return view('admin-views.diablero.task.list', compact('tasks', 'status', 'statuses', 'counts', 'zones'));
    }

    public function show($id)
    {
        $task = DiableroTask::with(['diablero', 'deliveryMan', 'loadingZone', 'zone', 'stops.store', 'stops.order.details', 'logs', 'group'])->findOrFail($id);
        $orders = Order::withoutGlobalScope(ZoneScope::class)->with(['delivery_man', 'store'])
            ->where('order_group_id', $task->order_group_id)->get();
        $availableDiableros = $task->status === DiableroTask::AVAILABLE
            ? Diablero::active()->where('zone_id', $task->zone_id)->orderByDesc('active')->get()
            : collect();

        return view('admin-views.diablero.task.view', compact('task', 'orders', 'availableDiableros'));
    }

    public function cancel(Request $request, $id)
    {
        $request->validate(['reason' => 'required|max:255']);
        $task = DiableroTask::findOrFail($id);
        if (in_array($task->status, DiableroTask::CLOSED_STATUSES, true)) {
            Toastr::warning(translate('messages.task_already_closed'));
            return back();
        }

        DiableroLogic::cancelTask($task, $request->reason, DiableroLogic::ACTOR_ADMIN, auth('admin')->id());

        if ($request->boolean('cancel_orders')) {
            $orders = Order::withoutGlobalScope(ZoneScope::class)->where('order_group_id', $task->order_group_id)->get();
            foreach ($orders as $order) {
                DiableroLogic::cancelOrder($order, $request->reason, 'admin');
            }
        }

        Toastr::success(translate('messages.task_canceled'));
        return back();
    }

    public function unassign($id)
    {
        $task = DiableroTask::findOrFail($id);
        if (!DiableroLogic::unassign($task, DiableroLogic::ACTOR_ADMIN, auth('admin')->id())) {
            Toastr::warning(translate('messages.task_can_not_be_unassigned_at_this_stage'));
            return back();
        }
        Toastr::success(translate('messages.task_unassigned'));
        return back();
    }

    public function assign(Request $request, $id)
    {
        $request->validate(['diablero_id' => 'required|exists:diableros,id']);
        $task = DiableroTask::findOrFail($id);
        $diablero = Diablero::active()->findOrFail($request->diablero_id);
        [$ok, $message] = DiableroLogic::assign($task, $diablero, auth('admin')->id());
        $ok ? Toastr::success($message) : Toastr::error($message);

        return back();
    }
}

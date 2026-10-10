@extends('layouts.admin.app')

@section('title', translate('messages.diablero_task') . ' #' . $task->id)

@section('content')
    @php($closed = in_array($task->status, \App\Models\DiableroTask::CLOSED_STATUSES, true))
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h1 class="page-header-title mb-0">{{ translate('messages.diablero_task') }} #{{ $task->id }}
                @include('admin-views.diablero.task.partials._status', ['status' => $task->status])</h1>
            <div class="d-flex gap-2">
                @if (in_array($task->status, ['accepted', 'arrived']))
                    <form action="{{ route('admin.diablero.task.unassign', $task->id) }}" method="post">
                        @csrf
                        <button class="btn btn--warning" type="submit">{{ translate('messages.unassign_diablero') }}</button>
                    </form>
                @endif
                @if (!$closed)
                    <button class="btn btn--danger" data-toggle="modal" data-target="#cancel-task-modal">{{ translate('messages.cancel_task') }}</button>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title">{{ translate('messages.pickup_stops') }}</h5></div>
                    <div class="card-body">
                        @foreach ($task->stops as $stop)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <div>
                                        <strong>{{ $stop->sequence }}. {{ $stop->store?->name }}</strong>
                                        <div class="text-muted small">{{ $stop->store?->address }}</div>
                                        <a href="{{ route('admin.order.details', $stop->order_id) }}">{{ translate('messages.order') }} #{{ $stop->order_id }}</a>
                                        <span class="badge badge-soft-dark">{{ translate('messages.' . ($stop->order?->order_status ?? '-')) }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge {{ ['collected' => 'badge-soft-success', 'failed' => 'badge-soft-danger', 'canceled' => 'badge-soft-danger'][$stop->status] ?? 'badge-soft-secondary' }}">
                                            {{ translate('messages.stop_' . $stop->status) }}</span>
                                        @if ($stop->failure_reason)
                                            <div class="small text-danger">{{ $stop->failure_reason }}</div>
                                        @endif
                                        @if ($stop->collected_at)
                                            <div class="small text-muted">{{ $stop->collected_at->format('d M Y, h:i A') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <table class="table table-sm table-borderless mt-2 mb-0">
                                    <tbody>
                                        @foreach ($stop->order?->details ?? [] as $detail)
                                            @php($info = json_decode($detail->item_details, true) ?? [])
                                            <tr>
                                                <td>{{ $info['name'] ?? '' }}</td>
                                                <td class="text-right">x {{ $detail->quantity }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title">{{ translate('messages.status_history') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-borderless table-thead-bordered card-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('messages.date') }}</th>
                                    <th>{{ translate('messages.from') }}</th>
                                    <th>{{ translate('messages.to') }}</th>
                                    <th>{{ translate('messages.by') }}</th>
                                    <th>{{ translate('messages.note') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($task->logs as $log)
                                    <tr>
                                        <td>{{ $log->created_at?->format('d M Y, h:i:s A') }}</td>
                                        <td>{{ $log->from_status ?? '-' }}</td>
                                        <td>{{ $log->to_status }}</td>
                                        <td>{{ $log->actor_type }}{{ $log->actor_id ? ' #' . $log->actor_id : '' }}</td>
                                        <td>{{ $log->note }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title">{{ translate('messages.task_summary') }}</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">{{ translate('messages.order_group') }}</dt><dd class="col-6">#{{ $task->order_group_id }}</dd>
                            <dt class="col-6">{{ translate('messages.zone') }}</dt><dd class="col-6">{{ $task->zone?->name }}</dd>
                            <dt class="col-6">{{ translate('messages.loading_zone') }}</dt><dd class="col-6">{{ $task->loadingZone?->name }}<br><small class="text-muted">{{ $task->loadingZone?->address }}</small></dd>
                            <dt class="col-6">{{ translate('messages.diablero_fee') }}</dt><dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($task->diablero_fee) }}</dd>
                            <dt class="col-6">{{ translate('messages.diablero_earning') }}</dt><dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($task->earning) }}</dd>
                            <dt class="col-6">{{ translate('messages.admin_commission') }}</dt><dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($task->diablero_fee - $task->earning) }}</dd>
                            <dt class="col-6">{{ translate('messages.handover_code') }}</dt><dd class="col-6"><code>{{ $task->handover_code }}</code></dd>
                            @if ($task->cancel_reason)
                                <dt class="col-6">{{ translate('messages.reason') }}</dt><dd class="col-6">{{ $task->cancel_reason }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title">{{ translate('messages.diablero') }}</h5></div>
                    <div class="card-body">
                        @if ($task->diablero)
                            <a href="{{ route('admin.diablero.view', $task->diablero_id) }}">{{ $task->diablero->full_name }}</a>
                            <div>{{ $task->diablero->phone }}</div>
                        @elseif ($task->status === 'available')
                            <form action="{{ route('admin.diablero.task.assign', $task->id) }}" method="post">
                                @csrf
                                <select name="diablero_id" class="form-control mb-2" required>
                                    <option value="">{{ translate('messages.select_diablero') }}</option>
                                    @foreach ($availableDiableros as $item)
                                        <option value="{{ $item->id }}">{{ $item->full_name }} ({{ $item->active ? translate('messages.online') : translate('messages.offline') }})</option>
                                    @endforeach
                                </select>
                                <button class="btn btn--primary w-100" type="submit">{{ translate('messages.assign') }}</button>
                            </form>
                        @else
                            <span class="text-muted">{{ translate('messages.unassigned') }}</span>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title">{{ translate('messages.handover') }}</h5></div>
                    <div class="card-body">
                        @php($dm = $task->deliveryMan ?? $orders->firstWhere('delivery_man_id', '!=', null)?->delivery_man)
                        <div>{{ translate('messages.deliveryman') }}: {{ $dm ? $dm->f_name . ' ' . $dm->l_name : translate('messages.not_assigned_yet') }}</div>
                        <div>{{ translate('messages.handed_over_at') }}: {{ $task->handed_over_at?->format('d M Y, h:i A') ?? '-' }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title">{{ translate('messages.orders') }}</h5></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($orders as $order)
                            <li class="list-group-item d-flex justify-content-between">
                                <a href="{{ route('admin.order.details', $order->id) }}">#{{ $order->id }} - {{ $order->store?->name }}</a>
                                <span class="badge badge-soft-dark">{{ translate('messages.' . $order->order_status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if (!$closed)
        <div class="modal fade" id="cancel-task-modal">
            <div class="modal-dialog">
                <form class="modal-content" action="{{ route('admin.diablero.task.cancel', $task->id) }}" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ translate('messages.cancel_task') }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true" class="tio-clear"></span></button>
                    </div>
                    <div class="modal-body">
                        <label class="input-label" for="cancel_reason">{{ translate('messages.reason') }} *</label>
                        <input type="text" id="cancel_reason" name="reason" class="form-control" maxlength="255" required>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="cancel_orders" name="cancel_orders" value="1">
                            <label class="form-check-label" for="cancel_orders">{{ translate('messages.also_cancel_and_refund_all_orders_in_this_group') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--danger">{{ translate('messages.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@php($diableroInfo = \App\CentralLogics\DiableroLogic::orderSummary($order))
@php($diableroTask = \App\CentralLogics\DiableroLogic::taskForOrder($order))
@if ($diableroInfo)
    <div class="card mb-2">
        <div class="card-body">
            <h5 class="card-title mb-3 d-flex flex-wrap align-items-center justify-content-between">
                <span class="d-flex align-items-center">
                    <span class="card-header-icon"><i class="tio-shopping-basket"></i></span>
                    <span>{{ translate('messages.diablero_pickup') }}</span>
                </span>
                @if ($diableroTask)
                    @include('admin-views.diablero.task.partials._status', ['status' => $diableroTask->status])
                @endif
            </h5>

            <ul class="list-unstyled mb-3">
                <li class="d-flex justify-content-between mb-1">
                    <span>{{ translate('messages.order_group') }}</span>
                    <strong>#{{ $diableroInfo['order_group_id'] }}</strong>
                </li>
                @if ($diableroTask)
                    <li class="d-flex justify-content-between mb-1">
                        <span>{{ translate('messages.task') }}</span>
                        @if (\App\CentralLogics\Helpers::module_permission_check('diablero'))
                            <a href="{{ route('admin.diablero.task.view', $diableroTask->id) }}">#{{ $diableroTask->id }}</a>
                        @else
                            <strong>#{{ $diableroTask->id }}</strong>
                        @endif
                    </li>
                @endif
                <li class="d-flex justify-content-between mb-1">
                    <span>{{ translate('messages.diablero') }}</span>
                    <strong>{{ $diableroInfo['diablero']['name'] ?? translate('messages.not_assigned') }}</strong>
                </li>
                @if ($diableroInfo['loading_zone'])
                    <li class="d-flex justify-content-between mb-1">
                        <span>{{ translate('messages.loading_zone') }}</span>
                        <strong class="text-right">{{ $diableroInfo['loading_zone']['name'] }}</strong>
                    </li>
                @endif
                <li class="d-flex justify-content-between mb-1">
                    <span>{{ translate('messages.group_total') }}</span>
                    <strong>{{ \App\CentralLogics\Helpers::format_currency($diableroInfo['group_order_amount']) }}</strong>
                </li>
            </ul>

            <h6 class="mb-2">{{ translate('messages.orders_in_this_group') }}</h6>
            <ul class="list-unstyled mb-3">
                @foreach ($diableroInfo['orders'] as $sibling)
                    <li class="d-flex justify-content-between mb-1 {{ $sibling['id'] == $order->id ? 'font-weight-bold' : '' }}">
                        <a href="{{ route('admin.order.details', $sibling['id']) }}">#{{ $sibling['id'] }} - {{ $sibling['store_name'] }}</a>
                        <span class="text-muted">{{ translate('messages.' . $sibling['order_status']) }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($diableroTask && $diableroTask->logs->count())
                <h6 class="mb-2">{{ translate('messages.timeline') }}</h6>
                <ul class="list-unstyled mb-0 small">
                    @foreach ($diableroTask->logs->sortBy('id') as $log)
                        <li class="mb-1">
                            <span class="text-muted">{{ $log->created_at?->format('d M H:i') }}</span>
                            &middot; {{ translate('messages.' . $log->to_status) }}
                            <span class="text-muted">({{ translate('messages.' . $log->actor_type) }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif

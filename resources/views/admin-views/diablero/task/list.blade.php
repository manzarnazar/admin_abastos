@extends('layouts.admin.app')

@section('title', translate('messages.diablero_tasks'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-shopping-basket-outlined"></i> {{ translate('messages.diablero_tasks') }}
                <span class="badge badge-soft-dark ml-2">{{ $tasks->total() }}</span></h1>
        </div>

        <ul class="nav nav-tabs mb-3 border-0 flex-wrap">
            @foreach ($statuses as $item)
                @php($count = $item === 'all' ? $counts->sum() : ($item === 'ongoing' ? collect(\App\Models\DiableroTask::ONGOING_STATUSES)->sum(fn ($s) => $counts[$s] ?? 0) : ($counts[$item] ?? 0)))
                <li class="nav-item">
                    <a class="nav-link {{ $status === $item ? 'active' : '' }}" href="{{ route('admin.diablero.task.list', array_merge(['status' => $item], request()->only('zone_id', 'search'))) }}">
                        {{ translate('messages.diablero_task_' . $item) }} <span class="badge badge-soft-dark">{{ $count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="card-header py-2 border-0">
                <form class="search--button-wrapper w-100" method="get">
                    <select name="zone_id" class="form-control w-auto" onchange="this.form.submit()">
                        <option value="">{{ translate('messages.all_zones') }}</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    <div class="input--group input-group input-group-merge input-group-flush">
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ translate('messages.search_by_task_group_or_order_id') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.task') }}</th>
                            <th>{{ translate('messages.order_group') }}</th>
                            <th>{{ translate('messages.zone') }}</th>
                            <th>{{ translate('messages.stops') }}</th>
                            <th>{{ translate('messages.diablero') }}</th>
                            <th>{{ translate('messages.loading_zone') }}</th>
                            <th>{{ translate('messages.diablero_fee') }}</th>
                            <th>{{ translate('messages.earning') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th>{{ translate('messages.created_at') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr>
                                <td><a href="{{ route('admin.diablero.task.view', $task->id) }}">#{{ $task->id }}</a></td>
                                <td>#{{ $task->order_group_id }}</td>
                                <td>{{ $task->zone?->name }}</td>
                                <td>{{ $task->stops_count }}</td>
                                <td>
                                    @if ($task->diablero)
                                        <a href="{{ route('admin.diablero.view', $task->diablero_id) }}">{{ $task->diablero->full_name }}</a>
                                    @else
                                        <span class="text-muted">{{ translate('messages.unassigned') }}</span>
                                    @endif
                                </td>
                                <td>{{ $task->loadingZone?->name }}</td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($task->diablero_fee) }}</td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($task->earning) }}</td>
                                <td>@include('admin-views.diablero.task.partials._status', ['status' => $task->status])</td>
                                <td>{{ $task->created_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-center">
                                    <a class="btn action-btn btn--warning btn-outline-warning" href="{{ route('admin.diablero.task.view', $task->id) }}"><i class="tio-visible"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($tasks->isEmpty())
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                        <h5>{{ translate('no_data_found') }}</h5>
                    </div>
                @endif
                <div class="page-area px-4 pb-3">{!! $tasks->links() !!}</div>
            </div>
        </div>
    </div>
@endsection

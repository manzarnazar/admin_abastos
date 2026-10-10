@extends('layouts.admin.app')

@section('title', $diablero->full_name)

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
            <h1 class="page-header-title"><i class="tio-user-big"></i> {{ $diablero->full_name }}</h1>
            <a href="{{ route('admin.diablero.edit', $diablero->id) }}" class="btn btn--primary"><i class="tio-edit"></i> {{ translate('messages.edit') }}</a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <img class="avatar avatar-xl rounded-circle onerror-image" src="{{ $diablero->image_full_url }}"
                            data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}" alt="">
                        <div>
                            <div class="font-weight-bold">{{ $diablero->full_name }}</div>
                            <div>{{ $diablero->phone }}</div>
                            <div class="text-muted">{{ $diablero->email }}</div>
                            <div>{{ translate('messages.zone') }}: {{ $diablero->zone?->name ?? '-' }}</div>
                            <span class="badge {{ $diablero->active ? 'badge-soft-success' : 'badge-soft-secondary' }}">
                                {{ $diablero->active ? translate('messages.online') : translate('messages.offline') }}</span>
                            <span class="badge {{ $diablero->status ? 'badge-soft-info' : 'badge-soft-danger' }}">
                                {{ $diablero->status ? translate('messages.active') : translate('messages.inactive') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="row g-3">
                    @foreach ([
                        ['messages.completed_tasks', $stats['completed']],
                        ['messages.ongoing_tasks', $stats['ongoing']],
                        ['messages.failed_or_canceled', $stats['failed']],
                        ['messages.total_earning', \App\CentralLogics\Helpers::format_currency($wallet->total_earning)],
                        ['messages.total_paid_out', \App\CentralLogics\Helpers::format_currency($wallet->total_withdrawn)],
                        ['messages.payable_balance', \App\CentralLogics\Helpers::format_currency($wallet->balance)],
                    ] as [$label, $value])
                        <div class="col-sm-4">
                            <div class="card card-body">
                                <h6 class="text-muted mb-1">{{ translate($label) }}</h6>
                                <h3 class="mb-0">{{ $value }}</h3>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title">{{ translate('messages.record_payout') }}</h5></div>
            <div class="card-body">
                <form action="{{ route('admin.diablero.payout', $diablero->id) }}" method="post" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="input-label" for="amount">{{ translate('messages.amount') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                        <input type="number" id="amount" name="amount" class="form-control" min="0.01" max="{{ $wallet->balance }}" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <label class="input-label" for="reference">{{ translate('messages.reference') }}</label>
                        <input type="text" id="reference" name="reference" class="form-control" maxlength="191">
                    </div>
                    <div class="col-md-4">
                        <label class="input-label" for="note">{{ translate('messages.note') }}</label>
                        <input type="text" id="note" name="note" class="form-control" maxlength="500">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn--primary w-100" {{ $wallet->balance > 0 ? '' : 'disabled' }}>{{ translate('messages.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3 border-0">
            <li class="nav-item"><a class="nav-link {{ $tab == 'tasks' ? 'active' : '' }}" href="{{ route('admin.diablero.view', [$diablero->id, 'tab' => 'tasks']) }}">{{ translate('messages.tasks') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ $tab == 'transactions' ? 'active' : '' }}" href="{{ route('admin.diablero.view', [$diablero->id, 'tab' => 'transactions']) }}">{{ translate('messages.earnings_and_payouts') }}</a></li>
        </ul>

        <div class="card">
            <div class="table-responsive">
                @if ($tab == 'transactions')
                    <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.date') }}</th>
                                <th>{{ translate('messages.type') }}</th>
                                <th>{{ translate('messages.reference') }}</th>
                                <th>{{ translate('messages.credit') }}</th>
                                <th>{{ translate('messages.debit') }}</th>
                                <th>{{ translate('messages.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $tx)
                                <tr>
                                    <td>{{ $tx->created_at?->format('d M Y, h:i A') }}</td>
                                    <td>{{ translate('messages.' . $tx->transaction_type) }}</td>
                                    <td>{{ $tx->reference }} @if ($tx->diablero_task_id)<a href="{{ route('admin.diablero.task.view', $tx->diablero_task_id) }}">#{{ $tx->diablero_task_id }}</a>@endif</td>
                                    <td class="text-success">{{ $tx->credit > 0 ? \App\CentralLogics\Helpers::format_currency($tx->credit) : '-' }}</td>
                                    <td class="text-danger">{{ $tx->debit > 0 ? \App\CentralLogics\Helpers::format_currency($tx->debit) : '-' }}</td>
                                    <td>{{ \App\CentralLogics\Helpers::format_currency($tx->balance) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="page-area px-4 pb-3">{!! $transactions->links() !!}</div>
                @else
                    <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.task') }}</th>
                                <th>{{ translate('messages.stops') }}</th>
                                <th>{{ translate('messages.loading_zone') }}</th>
                                <th>{{ translate('messages.earning') }}</th>
                                <th>{{ translate('messages.status') }}</th>
                                <th>{{ translate('messages.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tasks as $task)
                                <tr>
                                    <td><a href="{{ route('admin.diablero.task.view', $task->id) }}">#{{ $task->id }}</a></td>
                                    <td>{{ $task->stops_count }}</td>
                                    <td>{{ $task->loadingZone?->name }}</td>
                                    <td>{{ \App\CentralLogics\Helpers::format_currency($task->earning) }}</td>
                                    <td>@include('admin-views.diablero.task.partials._status', ['status' => $task->status])</td>
                                    <td>{{ $task->created_at?->format('d M Y, h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="page-area px-4 pb-3">{!! $tasks->links() !!}</div>
                @endif
            </div>
        </div>
    </div>
@endsection

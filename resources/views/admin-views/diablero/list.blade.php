@extends('layouts.admin.app')

@section('title', translate('messages.diableros'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-user-big"></i> {{ translate('messages.diableros') }}
                        <span class="badge badge-soft-dark ml-2">{{ $diableros->total() }}</span></h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn--primary" href="{{ route('admin.diablero.add') }}"><i class="tio-add"></i> {{ translate('messages.add_diablero') }}</a>
                </div>
            </div>
        </div>

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
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ translate('messages.search_by_name_phone_or_email') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.name') }}</th>
                            <th>{{ translate('messages.contact') }}</th>
                            <th>{{ translate('messages.zone') }}</th>
                            <th>{{ translate('messages.availability') }}</th>
                            <th>{{ translate('messages.completed_tasks') }}</th>
                            <th>{{ translate('messages.balance') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($diableros as $key => $diablero)
                            <tr>
                                <td>{{ $key + $diableros->firstItem() }}</td>
                                <td>
                                    <a class="d-flex align-items-center gap-2 text-body" href="{{ route('admin.diablero.view', $diablero->id) }}">
                                        <img class="avatar avatar-sm rounded-circle onerror-image" src="{{ $diablero->image_full_url }}"
                                            data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}" alt="">
                                        <span>{{ $diablero->full_name }}</span>
                                    </a>
                                </td>
                                <td>
                                    <div>{{ $diablero->phone }}</div>
                                    <small class="text-muted">{{ $diablero->email }}</small>
                                </td>
                                <td>{{ $diablero->zone?->name ?? '-' }}</td>
                                <td>
                                    @if ($diablero->active)
                                        <span class="badge badge-soft-success">{{ translate('messages.online') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('messages.offline') }}</span>
                                    @endif
                                </td>
                                <td>{{ $diablero->completed_tasks_count }}</td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($diablero->wallet?->balance ?? 0) }}</td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm">
                                        <input type="checkbox" class="toggle-switch-input redirect-url" data-url="{{ route('admin.diablero.status', [$diablero->id, $diablero->status ? 0 : 1]) }}"
                                            {{ $diablero->status ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--warning btn-outline-warning" href="{{ route('admin.diablero.view', $diablero->id) }}"><i class="tio-visible"></i></a>
                                        <a class="btn action-btn btn--primary btn-outline-primary" href="{{ route('admin.diablero.edit', $diablero->id) }}"><i class="tio-edit"></i></a>
                                        <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:" data-id="diablero-{{ $diablero->id }}"
                                            data-message="{{ translate('messages.want_to_remove_this_diablero') }}"><i class="tio-delete-outlined"></i></a>
                                        <form action="{{ route('admin.diablero.delete', $diablero->id) }}" method="post" id="diablero-{{ $diablero->id }}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($diableros->isEmpty())
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                        <h5>{{ translate('no_data_found') }}</h5>
                    </div>
                @endif
                <div class="page-area px-4 pb-3">{!! $diableros->links() !!}</div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $('.redirect-url').on('change', function () {
            location.href = $(this).data('url');
        });
    </script>
@endpush

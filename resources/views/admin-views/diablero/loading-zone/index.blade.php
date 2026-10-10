@extends('layouts.admin.app')

@section('title', translate('messages.loading_zones'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-map"></i> {{ translate('messages.loading_zones') }}</h1>
            <p class="text-muted mb-0">{{ translate('messages.loading_zones_are_the_points_where_diableros_hand_orders_to_deliverymen') }}</p>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">{{ $loadingZone ? translate('messages.edit_loading_zone') : translate('messages.add_loading_zone') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ $loadingZone ? route('admin.diablero.loading-zone.update', $loadingZone->id) : route('admin.diablero.loading-zone.store') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="form-group">
                                <label class="input-label" for="lz_zone_id">{{ translate('messages.zone') }} *</label>
                                <select id="lz_zone_id" name="zone_id" class="form-control" required>
                                    <option value="">{{ translate('messages.select_zone') }}</option>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}" {{ old('zone_id', $loadingZone?->zone_id ?? request('zone_id')) == $zone->id ? 'selected' : '' }}>
                                            {{ $zone->name }} {{ $zone->diablero_status ? '' : '(' . translate('messages.diablero_disabled') . ')' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="input-label" for="lz_name">{{ translate('messages.name') }} *</label>
                                <input type="text" id="lz_name" name="name" class="form-control" maxlength="191" required value="{{ old('name', $loadingZone?->name) }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label" for="lz_address">{{ translate('messages.address') }}</label>
                                <input type="text" id="lz_address" name="address" class="form-control" maxlength="255" value="{{ old('address', $loadingZone?->address) }}">
                            </div>
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label class="input-label" for="latitude">{{ translate('messages.latitude') }} *</label>
                                    <input type="number" step="any" id="latitude" name="latitude" class="form-control" required value="{{ old('latitude', $loadingZone?->latitude) }}">
                                </div>
                                <div class="col-6 form-group">
                                    <label class="input-label" for="longitude">{{ translate('messages.longitude') }} *</label>
                                    <input type="number" step="any" id="longitude" name="longitude" class="form-control" required value="{{ old('longitude', $loadingZone?->longitude) }}">
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="is_default" name="is_default" value="1" {{ old('is_default', $loadingZone?->is_default) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_default">{{ translate('messages.default_loading_zone_for_this_zone') }}</label>
                            </div>
                            <div class="btn--container justify-content-end">
                                @if ($loadingZone)
                                    <a href="{{ route('admin.diablero.loading-zone.index') }}" class="btn btn--reset">{{ translate('messages.cancel') }}</a>
                                @endif
                                <button type="submit" class="btn btn--primary">{{ $loadingZone ? translate('messages.update') : translate('messages.add') }}</button>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <input id="lz-search" class="form-control mb-2" type="text" placeholder="{{ translate('messages.search_location') }}">
                            <div id="lz-map" class="rounded border" style="height: 360px;"></div>
                            <small class="text-muted">{{ translate('messages.click_on_the_map_to_set_the_loading_zone_position') }}</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <form method="get" class="d-flex gap-2">
                    <select name="zone_id" class="form-control w-auto" onchange="this.form.submit()">
                        <option value="">{{ translate('messages.all_zones') }}</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.name') }}</th>
                            <th>{{ translate('messages.zone') }}</th>
                            <th>{{ translate('messages.address') }}</th>
                            <th>{{ translate('messages.coordinates') }}</th>
                            <th>{{ translate('messages.default') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($loadingZones as $key => $item)
                            <tr>
                                <td>{{ $key + $loadingZones->firstItem() }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->zone?->name }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($item->address, 40) }}</td>
                                <td><small>{{ $item->latitude }}, {{ $item->longitude }}</small></td>
                                <td>{!! $item->is_default ? '<span class="badge badge-soft-success">' . translate('messages.default') . '</span>' : '-' !!}</td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm">
                                        <input type="checkbox" class="toggle-switch-input redirect-url" data-url="{{ route('admin.diablero.loading-zone.status', [$item->id, $item->status ? 0 : 1]) }}" {{ $item->status ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary" href="{{ route('admin.diablero.loading-zone.edit', $item->id) }}"><i class="tio-edit"></i></a>
                                        <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:" data-id="lz-{{ $item->id }}"
                                            data-message="{{ translate('messages.want_to_delete_this_loading_zone') }}"><i class="tio-delete-outlined"></i></a>
                                        <form action="{{ route('admin.diablero.loading-zone.delete', $item->id) }}" method="post" id="lz-{{ $item->id }}">@csrf @method('delete')</form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($loadingZones->isEmpty())
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                        <h5>{{ translate('no_data_found') }}</h5>
                    </div>
                @endif
                <div class="page-area px-4 pb-3">{!! $loadingZones->links() !!}</div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $('.redirect-url').on('change', function () {
            location.href = $(this).data('url');
        });

        let lzMap, lzMarker;
        function setLzPosition(latLng) {
            $('#latitude').val(latLng.lat().toFixed(7));
            $('#longitude').val(latLng.lng().toFixed(7));
            if (lzMarker) {
                lzMarker.setPosition(latLng);
            } else {
                lzMarker = new google.maps.Marker({position: latLng, map: lzMap, draggable: true});
                lzMarker.addListener('dragend', e => setLzPosition(e.latLng));
            }
        }
        function initLoadingZoneMap() {
            const lat = parseFloat($('#latitude').val()) || {{ (float) (\App\CentralLogics\Helpers::get_business_settings('default_location')['lat'] ?? 19.4326) }};
            const lng = parseFloat($('#longitude').val()) || {{ (float) (\App\CentralLogics\Helpers::get_business_settings('default_location')['lng'] ?? -99.1332) }};
            const center = new google.maps.LatLng(lat, lng);
            lzMap = new google.maps.Map(document.getElementById('lz-map'), {center: center, zoom: 13});
            if ($('#latitude').val()) {
                setLzPosition(center);
            }
            lzMap.addListener('click', e => setLzPosition(e.latLng));

            const zoneId = $('#lz_zone_id').val();
            if (zoneId) {
                $.get('{{ url('admin/zone/get-coordinates') }}/' + zoneId, function (data) {
                    if (!data || !data.coordinates) return;
                    const polygon = new google.maps.Polygon({paths: data.coordinates, strokeWeight: 2, fillOpacity: 0.1, map: lzMap, clickable: false});
                    if (!$('#latitude').val() && data.center) lzMap.setCenter(data.center);
                });
            }

            if (google.maps.places) {
                const searchBox = new google.maps.places.SearchBox(document.getElementById('lz-search'));
                searchBox.addListener('places_changed', function () {
                    const place = (searchBox.getPlaces() || [])[0];
                    if (!place || !place.geometry) return;
                    lzMap.setCenter(place.geometry.location);
                    setLzPosition(place.geometry.location);
                    if (!$('#lz_address').val()) $('#lz_address').val(place.formatted_address || place.name);
                });
            }
        }
        $('#lz_zone_id').on('change', function () {
            if (window.google && google.maps) initLoadingZoneMap();
        });
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key') }}&libraries=places&callback=initLoadingZoneMap" async defer></script>
@endpush

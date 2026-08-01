@extends('layouts.admin.app')

@section('title',translate('Update Zone'))

@push('css_or_js')

@endpush

@section('content')

    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
                </span>
                <span>
                   {{ translate('edit_zone')}}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <form action="{{route('admin.business-settings.zone.update', $zone->id)}}" method="post" id="zone_form" class="shadow--card">
            @csrf
            <div class="row">
                <div class="col-md-5">
                    <div class="zone-setup-instructions">
                        <div class="zone-setup-top">
                            <h6 class="subtitle">{{ translate('Instructions') }}</h6>
                            <p>
                                {{ translate('Create_&_connect_dots_in_a_specific_area_on_the_map_to_add_a_new_business_zone.') }}
                            </p>
                        </div>
                        <div class="zone-setup-item">
                            <div class="zone-setup-icon">
                                <i class="tio-hand-draw"></i>
                            </div>
                            <div class="info">
                                {{ translate('Use_this_‘Hand_Tool’_to_find_your_target_zone.') }}
                            </div>
                        </div>
                        <div class="zone-setup-item">
                            <div class="zone-setup-icon">
                                <i class="tio-free-transform"></i>
                            </div>
                            <div class="info">
                                {{ translate('Use_this_‘Shape_Tool’_to_point_out_the_areas_and_connect_the_dots._Minimum_3_points/dots_are_required.') }}
                            </div>
                        </div>
                        <div class="instructions-image mt-4">
                            <img src="{{asset('public/assets/admin/img/instructions.gif')}}" alt="instructions">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-7 zone-setup">
                    <div class="form-group">
                        @if($language)
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active"
                                    href="#"
                                    id="default-link">{{translate('messages.default')}}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link"
                                            href="#"
                                            id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="pl-xl-5 pl-xxl-0">
                        @if($language)
                            <div class="row lang_form" id="default-form">
                                <div class="form-group col-6">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{ translate('messages.default') }})</label>
                                    <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_zone')}}" maxlength="191" value="{{$zone?->getRawOriginal('name')}}"  >
                                </div>
                                <div class="form-group col-6">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.display_name')}} ({{ translate('messages.default') }})</label>
                                    <input type="text" name="display_name[]" class="form-control" placeholder="{{translate('messages.display_name')}}" maxlength="191" value="{{$zone?->getRawOriginal('display_name')}}"  >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            </div>
                                @foreach($language as $lang)
                                    <?php
                                        if(count($zone['translations'])){
                                            $translate = [];
                                            foreach($zone['translations'] as $t)
                                            {
                                                if($t->locale == $lang && $t->key=="name"){
                                                    $translate[$lang]['name'] = $t->value;
                                                }
                                                if($t->locale == $lang && $t->key=="display_name"){
                                                    $translate[$lang]['display_name'] = $t->value;
                                                }
                                            }
                                        }
                                    ?>
                                <div class="row lang_form d-none" id="{{$lang}}-form">
                                    <div class="form-group col-6">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_zone')}}" maxlength="191" value="{{$translate[$lang]['name']??''}}"  >
                                    </div>
                                    <div class="form-group col-6">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.display_name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="display_name[]" class="form-control" placeholder="{{translate('messages.display_name')}}" maxlength="191" value="{{$translate[$lang]['display_name']??''}}"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                </div>
                                @endforeach
                            @endif
                        <div class="form-group d-none">
                            <label class="input-label" for="exampleFormControlInput1">{{ translate('messages.Coordinates') }}
                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('messages.draw_your_zone_on_the_map')}}">
                                    {{translate('messages.draw_your_zone_on_the_map')}}
                                </span>
                            </label>
                                <textarea type="text" rows="8" name="coordinates" id="coordinates" class="form-control" readonly>@foreach($zone->coordinates[0]->toArray()['coordinates'] as $key=>$coords)<?php if(count($zone->coordinates[0]->toArray()['coordinates']) != $key+1) {if($key != 0) echo(','); ?>({{$coords[1]}}, {{$coords[0]}})<?php } ?>@endforeach</textarea>
                        </div>


                        <div class="map-warper rounded mt-0">
                            <input id="pac-input" class="controls rounded initial--33" title="{{translate('messages.search_your_location_here')}}" type="text" placeholder="{{translate('messages.search_here')}}"/>
                            <div id="map-canvas" class="initial--34"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn--container mt-3 justify-content-end">
                <button id="reset_btn" type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                <button type="submit" class="btn btn--primary">{{translate('messages.Save_changes')}}</button>
            </div>
        </form>
    </div>

@endsection

@push('script_2')
<script src="{{ asset('public/assets/admin/js/view-pages/zone-map-drawing.js') }}"></script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key={{\App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value}}&callback=initialize&libraries=places&loading=async"></script>
<script>
    "use strict";
    auto_grow();
    function auto_grow() {
        let element = document.getElementById("coordinates");
        element.style.height = "5px";
        element.style.height = (element.scrollHeight)+"px";
    }

    let map;
    let lastpolygon = null;
    let polygons = [];
    let zoneMapInstance;

    function initialize() {
        const polygonCoords = [
            @foreach($area['coordinates'] as $coords)
             { lat: {{$coords[1]}}, lng: {{$coords[0]}} },
            @endforeach
        ];

        zoneMapInstance = ZoneMapDrawing.init({
            mapElementId: 'map-canvas',
            coordinatesSelector: '#coordinates',
            searchInputId: 'pac-input',
            defaultCenter: polygonCoords[0] || {
                lat: {{trim(explode(' ',$zone->center)[1], 'POINT()')}},
                lng: {{trim(explode(' ',$zone->center)[0], 'POINT()')}}
            },
            initialPaths: polygonCoords,
            initialPolygonOptions: { fillOpacity: 0 },
        });

        map = zoneMapInstance.map;
        Object.defineProperty(window, 'lastpolygon', {
            get: function () { return zoneMapInstance.lastPolygon; },
            set: function (value) { zoneMapInstance.lastPolygon = value; },
        });

        set_all_zones();
    }

    function set_all_zones()
    {
        $.get({
            url: '{{route('admin.zone.zoneCoordinates')}}/{{$zone->id}}',
            dataType: 'json',
            success: function (data) {

                console.log(data);
                for(let i=0; i<data.length;i++)
                {
                    polygons.push(new google.maps.Polygon({
                        paths: data[i],
                        strokeColor: "#FF0000",
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: "#FF0000",
                        fillOpacity: 0.1,
                    }));
                    polygons[i].setMap(map);
                }

            },
        });
    }
    $(document).on('ready', function(){
        if (map) {
            set_all_zones();
        }
        $("#zone_form").on('keydown', function(e){
            if (e.keyCode === 13) {
                e.preventDefault();
            }
        })
    });

    $('#reset_btn').click(function(){
        location.reload(true);
    })

</script>
@endpush

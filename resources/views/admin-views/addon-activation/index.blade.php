@extends('layouts.admin.app')

@section('title',translate('messages.add_on_activation'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
<div class="content container-fluid">

    <div class="d-content-between flex-wrap mb-20">
        <h2 class="title-clr">{{ translate('messages.add_on_activation') }}</h2>
     </div>
     <div class="d-flex flex-column gap-3">
         <div class="card">
            <form id="addon_activation_vendor_app_status_form" action="{{ route('admin.business-settings.addon-activation.activation') }}" method="post">
                @csrf
                <input type="hidden" name="addon_name" value="vendor_app">
                <input type="hidden" name="software_type" value="addon">
                <input type="hidden" name="software_id" value="MzY3NzIxNzM=">
                <input type="hidden" name="key" value="addon_activation_vendor_app">
                <div class="card-body p-20">
                    <div class="row align-items-center">
                        <div class="col-xxl-8 col-md-6 mb-md-0 mb-2">
                            <h4 class="black-color mb-1 d-block">{{ translate('messages.vendor_app') }}</h4>
                            <p class="fz-12 text-c mb-1">{{ translate('With_this_app_your_vendor_will_mange_their_business_through_mobile_app') }}</p>
                        </div>
                        @php($addon_activation_vendor_app = \App\Models\BusinessSetting::where('key', 'addon_activation_vendor_app')->first())
                        @php($addon_activation_vendor_app = $addon_activation_vendor_app?->value ? json_decode($addon_activation_vendor_app->value, true) : ['activation_status' => 0, 'username' => '', 'purchase_key' => ''])
                        <div class="col-xxl-4 col-md-6">
                            <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-sm-3 gap-2">
                                <div class="mb-0">
                                    <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox"
                                                    data-id="addon_activation_vendor_app_status"
                                                    data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/free-delivery-on.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/free-delivery-off.png') }}"
                                                    data-title-on="<strong>{{ translate('messages.want_to_Turn_ON_the_Deliveryman_App_addon?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('messages.want_to_Turn_OFF_the_Deliveryman_App_addon?') }}</strong>"
                                                    class="status toggle-switch-input dynamic-checkbox"
                                                name="status" id="addon_activation_vendor_app_status"
                                                value="1"
                                                {{ isset($addon_activation_vendor_app['activation_status']) && $addon_activation_vendor_app['activation_status'] == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
         <div class="card">
            <form id="addon_activation_delivery_man_app_status_form" action="{{ route('admin.business-settings.addon-activation.activation') }}" method="post">
                @csrf
                <input type="hidden" name="addon_name" value="deliveryman_app">
                <input type="hidden" name="software_type" value="addon">
                <input type="hidden" name="software_id" value="MzY3NzIxNDg=">
                <input type="hidden" name="key" value="addon_activation_delivery_man_app">
                <div class="card-body p-20">
                    <div class="row align-items-center">
                        <div class="col-xxl-8 col-md-6 mb-md-0 mb-2">
                            <h4 class="black-color mb-1 d-block">{{ translate('messages.deliveryman_app') }}</h4>
                            <p class="fz-12 text-c mb-1">{{ translate('with_this_app_your_all_your_deliveryman_will_mange_their_orders_through_mobile_app') }}</p>
                        </div>
                        @php($addon_activation_delivery_man_app = \App\Models\BusinessSetting::where('key', 'addon_activation_delivery_man_app')->first())
                        @php($addon_activation_delivery_man_app = $addon_activation_delivery_man_app?->value ? json_decode($addon_activation_delivery_man_app->value, true) : ['activation_status' => 0, 'username' => '', 'purchase_key' => ''])
                        <div class="col-xxl-4 col-md-6">
                            <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-sm-3 gap-2">
                                <div class="mb-0">
                                    <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox"
                                                    data-id="addon_activation_delivery_man_app_status"
                                                    data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/free-delivery-on.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/free-delivery-off.png') }}"
                                                    data-title-on="<strong>{{ translate('messages.want_to_Turn_ON_the_Deliveryman_App_addon?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('messages.want_to_Turn_OFF_the_Deliveryman_App_addon?') }}</strong>"
                                                    class="status toggle-switch-input dynamic-checkbox"
                                                name="status" id="addon_activation_delivery_man_app_status"
                                                value="1"
                                                {{ isset($addon_activation_delivery_man_app['activation_status']) && $addon_activation_delivery_man_app['activation_status'] == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
         <div class="card">
            <form id="addon_activation_react_status_form" action="{{ route('admin.business-settings.addon-activation.activation') }}" method="post">
                @csrf
                <input type="hidden" name="addon_name" value="react_web">
                <input type="hidden" name="software_type" value="addon">
                <input type="hidden" name="software_id" value="NDUzNzAzNTE=">
                <input type="hidden" name="key" value="addon_activation_react">
                <div class="card-body p-20">
                    <div class="row align-items-center">
                        <div class="col-xxl-8 col-md-6 mb-md-0 mb-2">
                            <h4 class="black-color mb-1 d-block">{{ translate('messages.react_user_website') }}</h4>
                            <p class="fz-12 text-c mb-1">{{ translate('with_this_react_website_your_customers_will_experience_your_system_in_a_more_attractive_and_seamless_way') }}</p>
                        </div>
                        @php($addon_activation_react = \App\Models\BusinessSetting::where('key', 'addon_activation_react')->first())
                        @php($addon_activation_react = $addon_activation_react?->value ? json_decode($addon_activation_react->value, true) : ['activation_status' => 0, 'username' => '', 'purchase_key' => ''])
                        <div class="col-xxl-4 col-md-6">
                            <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end align-items-center gap-sm-3 gap-2">
                                <div class="mb-0">
                                    <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox"
                                                    data-id="addon_activation_react_status"
                                                    data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/free-delivery-on.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/free-delivery-off.png') }}"
                                                    data-title-on="<strong>{{ translate('messages.want_to_Turn_ON_the_Deliveryman_App_addon?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('messages.want_to_Turn_OFF_the_Deliveryman_App_addon?') }}</strong>"
                                                    class="status toggle-switch-input dynamic-checkbox"
                                                name="status" id="addon_activation_react_status"
                                                value="1"
                                                {{ isset($addon_activation_react['activation_status']) && $addon_activation_react['activation_status'] == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
     </div>
</div>

@endsection

@push('script_2')

@endpush

@extends('layouts.admin.app')

@section('title', $diablero ? translate('messages.edit_diablero') : translate('messages.add_diablero'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-user-big"></i>
                {{ $diablero ? translate('messages.edit_diablero') : translate('messages.add_diablero') }}</h1>
        </div>

        <form action="{{ $diablero ? route('admin.diablero.update', $diablero->id) : route('admin.diablero.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title">{{ translate('messages.general_information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="input-label" for="f_name">{{ translate('messages.first_name') }} *</label>
                            <input type="text" id="f_name" name="f_name" class="form-control" maxlength="100" required value="{{ old('f_name', $diablero?->f_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label" for="l_name">{{ translate('messages.last_name') }}</label>
                            <input type="text" id="l_name" name="l_name" class="form-control" maxlength="100" value="{{ old('l_name', $diablero?->l_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label" for="zone_id">{{ translate('messages.zone') }} *</label>
                            <select id="zone_id" name="zone_id" class="form-control" required>
                                <option value="">{{ translate('messages.select_zone') }}</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}" {{ old('zone_id', $diablero?->zone_id) == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="input-label" for="image">{{ translate('messages.image') }}</label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/*">
                            @if ($diablero?->image)
                                <img class="mt-2 rounded" height="60" src="{{ $diablero->image_full_url }}" alt="">
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="input-label" for="identity_type">{{ translate('messages.identity_type') }}</label>
                            <select id="identity_type" name="identity_type" class="form-control">
                                @foreach (['passport', 'driving_license', 'nid', 'ine'] as $type)
                                    <option value="{{ $type }}" {{ old('identity_type', $diablero?->identity_type) == $type ? 'selected' : '' }}>{{ translate('messages.' . $type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="input-label" for="identity_number">{{ translate('messages.identity_number') }}</label>
                            <input type="text" id="identity_number" name="identity_number" class="form-control" maxlength="30" value="{{ old('identity_number', $diablero?->identity_number) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="input-label" for="identity_image">{{ translate('messages.identity_image') }}</label>
                            <input type="file" id="identity_image" name="identity_image[]" class="form-control" accept="image/*" multiple>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title">{{ translate('messages.account_information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="input-label" for="phone">{{ translate('messages.phone') }} *</label>
                            <input type="text" id="phone" name="phone" class="form-control" maxlength="20" required value="{{ old('phone', $diablero?->phone) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="input-label" for="email">{{ translate('messages.email') }}</label>
                            <input type="email" id="email" name="email" class="form-control" maxlength="100" value="{{ old('email', $diablero?->email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="input-label" for="password">{{ translate('messages.password') }} {{ $diablero ? '' : '*' }}</label>
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" {{ $diablero ? '' : 'required' }}
                                placeholder="{{ translate('messages.min_8_characters_with_letters_and_numbers') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn--container justify-content-end">
                <a href="{{ route('admin.diablero.list') }}" class="btn btn--reset">{{ translate('messages.back') }}</a>
                <button type="submit" class="btn btn--primary">{{ translate('messages.submit') }}</button>
            </div>
        </form>
    </div>
@endsection

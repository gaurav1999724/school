@extends('layouts.master')

@section('title')
    {{ __('general_settings') }}
@endsection


@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('general_settings') }}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form id="formdata" class="create-form-without-reset" action="{{ route('system-settings.store') }}" method="POST" novalidate="novalidate" enctype="multipart/form-data">
                            @csrf
                            {{-- System Settings --}}
                            <div class="border border-secondary rounded-lg my-4 mx-1">
                                <div class="col-md-12 mt-3">
                                    <h4>{{ __('System Settings') }}</h4>
                                </div>
                                <div class="col-12 mb-3">
                                    <hr class="mt-0">
                                </div>
                                <div class="row my-4 mx-1">
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="system_name">{{ __('system_name') }} <span class="text-danger">*</span></label>
                                        <input name="system_name" id="system_name" value="{{ $settings['system_name'] ?? '' }}" type="text" required placeholder="{{ __('system_name') }}" class="form-control"/>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="mobile">{{ __('mobile') }} <span class="text-danger">*</span></label>
                                        <input name="mobile" id="mobile" value="{{ $settings['mobile'] ?? '' }}" type="number" required placeholder="{{ __('mobile') }}" class="form-control"/>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="tag_line">{{ __('tag_line') }} <span class="text-danger">*</span></label>
                                        <input name="tag_line" id="tag_line" value="{{ $settings['tag_line'] ?? '' }}" type="text" required placeholder="{{ __('tag_line') }}" class="form-control"/>
                                    </div>

                                    <div class="form-group col-md-6 col-sm-12">
                                        <label for="hero_description">{{ __('description') }} <span class="text-danger">*</span></label>
                                        <textarea name="hero_description" id="hero_description" required placeholder="{{ __('description') }}" class="form-control">{{ $settings['hero_description'] ?? null }}</textarea>
                                    </div>

                                    <div class="form-group col-md-6 col-sm-12">
                                        <label for="address">{{ __('address') }} <span class="text-danger">*</span></label>
                                        <textarea name="address" id="address" required placeholder="{{ __('address') }}" class="form-control">{{ $settings['address'] ?? null }}</textarea>
                                    </div>

                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="time_zone">{{ __('time_zone') }}</label>
                                        <select name="time_zone" id="time_zone" required class="form-control"
                                                style="width:100%">
                                            @foreach ($getTimezoneList as $timezone)
                                                <option value="{{ $timezone[2] }}"{{ isset($settings['time_zone']) && $settings['time_zone'] == $timezone[2] ? 'selected' : '' }}>{{ $timezone[2] . ' - GMT ' . $timezone[1] . ' - ' . $timezone[0] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="date_format">{{ __('date_format') }}</label>
                                        <select name="date_format" id="date_format" required class="form-control">
                                            @foreach ($getDateFormat as $key => $dateformat)
                                                <option value="{{ $key }}"{{ isset($settings['date_format']) && $settings['date_format'] == $key ? 'selected' : '' }}>{{ $dateformat }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="time_format">{{ __('time_format') }}</label>
                                        <select name="time_format" id="time_format" required class="form-control">
                                            @foreach ($getTimeFormat as $key => $timeFormat)
                                                <option value="{{ $key }}"{{ isset($settings['time_format']) && $settings['time_format'] == $key ? 'selected' : '' }}>{{ $timeFormat }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="row my-4 mx-1">
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="favicon">{{ __('favicon') }} <span class="text-danger">*</span></label>
                                        <input type="file" name="favicon" class="file-upload-default"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" id="favicon" class="form-control file-upload-info" disabled="" placeholder="{{ __('favicon') }}"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                            <div class="col-md-12 mt-2">
                                                <img height="50px" src='{{ $settings['favicon'] ?? '' }}' alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="horizontal_logo">{{ __('horizontal_logo') }} <span class="text-danger">*</span></label>
                                        <input type="file" name="horizontal_logo" class="file-upload-default"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" id="horizontal_logo" class="form-control file-upload-info" disabled="" placeholder="{{ __('horizontal_logo') }}"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                            <div class="col-md-12 mt-2">
                                                <img height="50px" src='{{ $settings['horizontal_logo'] ?? '' }}' alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="vertical_logo">{{ __('vertical_logo') }} <span class="text-danger">*</span></label>
                                        <input type="file" name="vertical_logo" class="file-upload-default"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" id="vertical_logo" disabled="" placeholder="{{ __('vertical_logo') }}"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                            <div class="col-md-12 mt-2">
                                                <img height="50px" src='{{ $settings['vertical_logo'] ?? '' }}' alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="login_page_logo">{{ __('login_page_logo') }} <span class="text-danger">*</span></label>
                                        <input type="file" name="login_page_logo" class="file-upload-default" />
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" id="login_page_logo" disabled="" placeholder="{{ __('login_page_logo') }}" />
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                            <div class="col-md-12 mt-2">
                                                <img height="50px" src='{{ $settings['login_page_logo'] ?? '' }}' alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="theme_color">{{ __('color') }}</label>
                                        <input name="theme_color" id="theme_color" value="{{ $settings['theme_color'] ?? '' }}" type="text" required placeholder="{{ __('color') }}" class="color-picker"/>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="School Code Prefix">{{ __('School Code Prefix') }} <span class="text-danger">*</span></label>
                                        <input name="school_code_prefix" id="school_code_prefix" value="{{ $settings['school_code_prefix'] ?? 'SCH' }}" type="text" required placeholder="{{ __('School Code Prefix') }}" class="form-control"/>
                                    </div>


                                </div>

                            </div>
                            {{-- End System Settings --}}

                            <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit" value={{ __('submit') }}>
                            <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

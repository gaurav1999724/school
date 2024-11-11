@extends('layouts.master')

@section('title')
    {{__('notification_settings')}}
@endsection


@section('content')

    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{__('notification_settings')}}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form id="formdata" class="edit-form" action="{{route('notification-setting.update')}}" method="POST" novalidate="novalidate">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-6 col-sm-12">
                                    <label for="firebase_project_id">{{ __('firebase_project_id') }}</label>
                                    <input name="firebase_project_id" id="firebase_project_id" value="{{ $project_id ?? '' }}"  required placeholder="{{ __('firebase_project_id') }}" class="form-control"/>
                                </div>
                                <div class="form-group col-md-6 col-sm-12">
                                    <label>{{ __('firebase_service_file') }} <span class="text-info text-small">{{ __('Only Json File Allowed') }}</span></label>
                                    <input type="file" name="firebase_service_file" class="file-upload-default" accept="application/json"/>
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" accept="application/json" disabled="" placeholder="{{ __('upload_file') }}" aria-label=""/>
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                        </span>
                                    </div>
                                    <a href="{{ $serviceFile ?? '' }}"> {{ __('Service File') }}</a>
                                </div>
                            </div>
                            <input class="btn btn-theme float-right" type="submit" value="{{ __('submit') }}">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

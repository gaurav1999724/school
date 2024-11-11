@extends('layouts.master')

@section('title')
    {{ __('email_template') }}
@endsection


@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('email_template') }}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form id="formdata" class="setting-form" action="{{ route('system-settings.update', 1) }}"
                            method="POST" novalidate="novalidate">
                            @csrf
                            <div class="row">
                                
                                <input type="hidden" name="name" id="name" value="{{ $name }}">
                                <label for="data"></label>
                                <div class="form-group col-md-12 col-sm-12">
                                    <textarea id="tinymce_message" name="data" id="data" required placeholder="{{ __('email_template') }}">{{ $data ?? '' }}</textarea>
                                </div>
                                
                                <div class="form-group col-sm-12 col-md-12">
                                    <a data-value="{school_admin_name}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('school_admin_name') }} }</a>
                                    <a data-value="{code}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('code') }} }</a>
                                    <a data-value="{email}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('email') }} }</a>
                                    <a data-value="{password}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('password') }} }</a>
                                    <a data-value="{school_name}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('school_name') }} }</a>
                                </div>
                                

                                <div class="form-group col-sm-12 col-md-12">
                                    <hr>
                                    <a data-value="{super_admin_name}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('super_admin_name') }} }</a>
                                    <a data-value="{support_email}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('support_email') }} }</a>
                                    <a data-value="{contact}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('contact') }} }</a>
                                    <a data-value="{system_name}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('system_name') }} }</a>
                                    <a data-value="{url}" class="btn btn-gradient-light btn_tag mt-2">{ {{ __('url') }} }</a>
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

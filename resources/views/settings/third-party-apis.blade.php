@extends('layouts.master')

@section('title')
    {{ __('Third-Party APIs') }}
@endsection


@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('Third-Party APIs') }}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="custom-card-body">
                        <form id="formdata" class="create-form-without-reset" action="{{ route('system-settings.third-party.update') }}" method="POST" novalidate="novalidate" enctype="multipart/form-data">
                            @csrf
                            {{-- System Settings --}}
                            <div class="border border-secondary rounded-lg my-4 mx-1">
                                <div class="col-md-12 mt-3">
                                    <h4>{{ __('google_recaptcha') }}</h4>
                                </div>
                                <div class="col-12 mb-3">
                                    <hr class="mt-0">
                                </div>
                                <div class="row my-4 mx-1">
                                    {{-- <div class="form-group col-md-4 col-sm-12">
                                        <label for="RECAPTCHA_SITE">{{ __('RECAPTCHA_SITE') }} <span class="text-danger">*</span></label>
                                        <input name="RECAPTCHA_SITE" id="RECAPTCHA_SITE" value="{{ env('RECAPTCHA_SITE') ?? '' }}" type="text" placeholder="{{ __('RECAPTCHA_SITE') }}" class="form-control"/>
                                    </div> --}}
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="RECAPTCHA_SITE_KEY">{{ __('RECAPTCHA_SITE_KEY') }}</label>
                                        <input name="RECAPTCHA_SITE_KEY" id="RECAPTCHA_SITE_KEY" value="{{ env('RECAPTCHA_SITE_KEY') ?? '' }}" type="text" placeholder="{{ __('RECAPTCHA_SITE_KEY') }}" class="form-control"/>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="RECAPTCHA_SECRET_KEY">{{ __('RECAPTCHA_SECRET_KEY') }}</label>
                                        <input name="RECAPTCHA_SECRET_KEY" id="RECAPTCHA_SECRET_KEY" value="{{ env('RECAPTCHA_SECRET_KEY') ?? '' }}" type="text" placeholder="{{ __('RECAPTCHA_SECRET_KEY') }}" class="form-control"/>
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

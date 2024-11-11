@extends('layouts.master')

@section('title')
    {{__('email_configuration')}}
@endsection


@section('content')

    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{__('email_configuration')}}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-5">
                            {{__('add_email_configuration')}}
                        </h4>
                        <form id="verify_email" action="{{route('system-settings.email.update')}}" method="POST" novalidate="novalidate">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="mail_mailer">{{__('mail_mailer')}}</label>
                                    <select required name="mail_mailer" id="mail_mailer" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                        <option value="">--- Select Mailer ---</option>
                                        <option {{env('MAIL_MAILER')=='smtp' ?'selected':''}} value="smtp">SMTP</option>
                                        <option {{env('MAIL_MAILER')=='mailgun' ?'selected':''}} value="mailgun">Mailgun</option>
                                        <option {{env('MAIL_MAILER')=='sendmail' ?'selected':''}} value="sendmail">sendmail</option>
                                        <option {{env('MAIL_MAILER')=='postmark' ?'selected':''}} value="postmark">Postmark</option>
                                        <option {{env('MAIL_MAILER')=='amazon_ses' ?'selected':''}} value="amazon_ses">Amazon SES</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="mail_host">{{__('mail_host')}}</label>
                                    <input name="mail_host" id="mail_host" value="{{env('MAIL_HOST')}}" type="text" required placeholder="{{__('mail_host')}}" class="form-control"/>
                                </div>
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="mail_port">{{__('mail_port')}}</label>
                                    <input name="mail_port" id="mail_port" value="{{env('MAIL_PORT')}}" type="text" required placeholder="{{__('mail_port')}}" class="form-control"/>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="mail_username">{{__('mail_username')}}</label>
                                    <input name="mail_username" id="mail_username" value="{{env('MAIL_USERNAME')}}" type="text" required placeholder="{{__('mail_username')}}" class="form-control"/>
                                </div>
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="password">{{__('mail_password')}}</label>
                                    <div class="input-group">
                                        <input id="password" name="mail_password" value="{{env('MAIL_PASSWORD')}}" type="password" required placeholder="{{__('mail_password')}}" class="form-control"/>
                                        <div class="input-group-append" id="togglePasswordShowHide">
                                        <span class="input-group-text">
                                            <i class="fa fa-eye-slash" id="togglePassword"></i>
                                        </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="mail_encryption">{{__('mail_encryption')}}</label>
                                    <input name="mail_encryption" id="mail_encryption" value="{{env('MAIL_ENCRYPTION')}}" type="text" required placeholder="{{__('mail_encryption')}}" class="form-control"/>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="mail_send_from">{{__('mail_send_from')}}</label>
                                    <input name="mail_send_from" id="mail_send_from" value="{{env('MAIL_FROM_ADDRESS')}}" type="text" required placeholder="{{__('mail_send_from')}}" class="form-control"/>
                                </div>
                            </div>
                            <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit" value={{ __('submit') }}>
                            <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-3">
                            {{__('Email Configuration Verification')}}
                        </h4>
                        <div class="mb-2">
                            <span class="text-danger ">{{ __('NOTE : An email will be sent to test if your email settings are correct') }}</span>
                        </div>
                        <div class="mb-2">
                            <h5>{{ __('Steps') }} : </h5>
                            <div>
                                <ol>
                                    <li>{{ __('Enter the email address in the input box.(Do not enter the same email address which you have used for Email Configuration)') }}.</li>
                                    <li>{{__('Click on Verify')}}</li>
                                    <li>{{ __('Check your inbox if you have received a Testing Email then your Email Configuration are Correct Congratulations Email Setup is done') }}.</li>

                                </ol>
                            </div>
                        </div>

                        <form id="send_verification_email" action="{{route('system-settings.email.verify')}}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-4 col-sm-12">
                                    <label for="verify_email_address">{{__('email')}}</label>
                                    <input name="verify_email" id="verify_email_address" type="email" required placeholder="{{__('email')}}" class="form-control"/>
                                </div>
                                <div class="form-group col-px-md-5">
                                    <input class="btn btn-theme m-4" type="submit" value="{{ __('Verify') }}">
                                </div>

                                <div id="error-div" style="display: none;" class="col-12">
                                    <h6>Error : </h6>
                                    <pre id="error"></pre>
                                    <h6>Stacktrace : </h6>
                                    <pre id="stacktrace"></pre>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        const togglePassword = document.querySelector("#togglePasswordShowHide");
        const password = document.querySelector("#password");

        togglePassword.addEventListener("click", function () {
            const type = password.getAttribute("type") === "password" ? "text" : "password";
            password.setAttribute("type", type);
            // this.classList.toggle("fa-eye");
            if (password.getAttribute("type") === 'password') {
                $('#togglePassword').addClass('fa-eye-slash');
                $('#togglePassword').removeClass('fa-eye');
            } else {
                $('#togglePassword').removeClass('fa-eye-slash');
                $('#togglePassword').addClass('fa-eye');
            }
        });
    </script>
@endsection

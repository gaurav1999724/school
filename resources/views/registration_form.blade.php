<!-- Button trigger modal -->
{{-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
    Launch static backdrop modal
</button> --}}
<div class="modal fade formModal" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered  modal-xl">
        <div class="modal-content row">
            <div class="col-12 rightSide">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="staticBackdropLabel">{{ __('registration_form') }}</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="school-registration" action="{{ url('schools/registration') }}" method="post">
                        @csrf
                        <div class="schoolFormWrapper">
                            <div class="headingWrapper">
                                <span>{{ __('create_school') }}</span>
                            </div>
                            <div class="formWrapper">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="name">{{ __('name') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="school_name" id="name" placeholder="{{ __('enter_your_school_name') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="supportEmail">{{ __('email') }} <span class="text-danger">*</span></label>
                                            <input type="email" name="school_support_email" id="support-email"
                                                placeholder="{{ __('enter_your_school_email') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="supportPhone">{{ __('mobile') }} <span class="text-danger">*</span></label>
                                            <input type="text" oninput="this.value=this.value.replace(/[^0-9]/g,'');" name="school_support_phone" id="supportPhone"
                                                placeholder="{{ __('enter_your_school_mobile_number') }}" maxlength="16" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="address">{{ __('address') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="school_address" id="address"
                                                placeholder="{{ __('enter_your_school_address') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="inputWrapper">
                                            <label for="tagline">{{ __('tagline') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="school_tagline" id="tagline" placeholder="{{ __('tagline') }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="adminFormWrapper schoolFormWrapper">
                            <div class="headingWrapper">
                                <span>{{ __('add_admin') }}</span>
                            </div>
                            <div class="formWrapper">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="FirstName">{{ __('first_name') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="admin_first_name" id="firstName"
                                                placeholder="{{ __('enter_your_first_name') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="lastName">{{ __('last_name') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="admin_last_name" id="lastName"
                                                placeholder="{{ __('enter_your_last_name') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="adminEmail">{{ __('email') }} <span class="text-danger">*</span></label>
                                            <input type="email" name="admin_email" id="adminEmail"
                                                placeholder="{{ __('enter_your_email') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="inputWrapper">
                                            <label for="contact">{{ __('contact') }} <span class="text-danger">*</span></label>
                                            <input type="text" oninput="this.value=this.value.replace(/[^0-9]/g,'');" maxlength="16" name="admin_contact" id="contact"
                                                placeholder="{{ __('enter_your_contact_number') }}" required>
                                        </div>
                                    </div>
                                    @if ($trail_package)
                                    <div class="col-lg-6">
                                        
                                        <div class="">
                                            {!! Form::checkbox('trial_package', $trail_package, false, ['class' => 'm-1']) !!}
                                            {{ __('start_trial_package') }}
                                        </div>
                                        
                                    </div>    
                                    @endif

                                    @if (config('services.recaptcha.key') ?? '')
                                        <div class="col-lg-12">
                                            <div class="g-recaptcha mt-4" data-sitekey={{config('services.recaptcha.key')}}></div>
                                        </div>    
                                    @endif
                                    
                                    
                                    <div class="col-12 modalfooter">

                                        <div class="inputWrapper">
                                            
                                        </div>
                                        <div>
                                            <input type="submit" class="commonBtn" value="{{ __('submit') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
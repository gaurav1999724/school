@extends('layouts.master')

@section('title')
    {{ __('schools') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage') . ' ' . __('schools') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <form class="create-form school-registration-form school-registration-validate" enctype="multipart/form-data" action="{{ route('schools.store') }}" method="POST" novalidate="novalidate">
                            @csrf
                            <div class="bg-light p-4 mt-4">
                                <h4 class="card-title mb-4">
                                    {{ __('create') . ' ' . __('schools') }}
                                </h4>
                                <div class="row">
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="school_name">{{ __('name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="school_name" id="school_name" placeholder="{{__('schools')}}" class="form-control" required>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label>{{ __('logo') }} <span class="text-danger">*</span></label>
                                        <input type="file" required name="school_image" id="school_image" class="file-upload-default" accept="image/png, image/jpg, image/jpeg, image/svg+xml"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('logo') }}" required aria-label=""/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="school_support_email">{{ __('school').' '.__('email') }} <span class="text-danger">*</span></label>
                                        <input type="email" name="school_support_email" id="school_support_email" placeholder="{{__('support').' '.__('email')}}" class="form-control" required>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="school_support_phone">{{ __('school').' '.__('phone') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="school_support_phone" maxlength="16" id="school_support_phone" placeholder="{{__('support').' '.__('phone')}}" min="0" class="form-control remove-number-increment" required>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="school_tagline">{{ __('tagline')}} <span class="text-danger">*</span></label>
                                        <textarea name="school_tagline" id="school_tagline" cols="30" rows="3" class="form-control" placeholder="{{__('tagline')}}" required></textarea>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="school_address">{{ __('address')}} <span class="text-danger">*</span></label>
                                        <textarea name="school_address" id="school_address" cols="30" rows="3" class="form-control" placeholder="{{__('address')}}" required></textarea>
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="school_domain">{{ __('domain')}}</label>
                                        <div class="input-group mb-3">
                                                <input type="text" class="form-control domain-pattern" name="domain" placeholder="{{ __('domain') }}" aria-label="Recipient's username" aria-describedby="basic-addon2">
                                            <div class="input-group-append">
                                                <span class="input-group-text text-body" id="basic-addon2">.{{ $baseUrlWithoutScheme }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- <div class="form-group col-sm-12 col-md-3">
                                        <label for="assign_package">{{ __('assign_package')}} </label>
                                        {!! Form::select('assign_package', $packages, null, ['class' => 'form-control', 'placeholder' => __('select_package')]) !!}
                                    </div> --}}

                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="school_domain">{{ __('School Code Prefix')}}</label> <span class="text-danger">*</span>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control school_code_prefix" id="school_code_prefix" name="school_code_prefix" required placeholder="{{ __('prefix') }}" value="{{ $prefix }}">
                                            <div class="input-group-append">
                                                <input type="text" class="input-group-text text-body school_code" id="basic-addon2" name="school_code" value="{{ $school_code }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="bg-light p-4 mt-4">
                                <h4 class="card-title mb-4">
                                    {{ __('add') . ' ' . __('admin') }}
                                </h4>
                                <div class="row">
                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="admin_first_name">{{ __('first_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="admin_first_name" id="admin_first_name" placeholder="{{__('first_name')}}" class="form-control" required>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="admin_last_name">{{ __('last_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="admin_last_name" id="admin_last_name" placeholder="{{__('last_name')}}" class="form-control" required>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="admin_contact">{{ __('contact') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="admin_contact" id="admin_contact" min="0" placeholder="{{__('contact')}}" class="form-control remove-number-increment" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label for="admin_email">{{__('email') }} <span class="text-danger">*</span></label>
                                        <input type="email" name="admin_email" id="admin_email" placeholder="{{__('email')}}" class="form-control" required>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-6">
                                        <label>{{ __('image') }} <span class="text-danger">*</span></label>
                                        <input type="file" required name="admin_image" class="file-upload-default" accept="image/png, image/jpg, image/jpeg, image/svg+xml"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('image') }}" required aria-label=""/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit" value={{ __('submit') }}>
                            <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list') . ' ' . __('schools') }}
                        </h4>
                        <div class="row" id="toolbar">
                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu" for="package">{{ __('package') }}</label>
                                {!! Form::select('package', ['' => 'All'] + $packages, null, ['class' => 'form-control','id' => 'filter_package_id']) !!}
                            </div>
                        </div>
                        <div class="col-12 text-right">
                            <b><a href="#" class="table-list-type active mr-2" data-id="0">{{__('all')}}</a></b> | <a href="#" class="ml-2 table-list-type" data-id="1">{{__("Trashed")}}</a>
                        </div>
                        <table aria-describedby="mydesc" class='table' id='table_list'
                               data-toggle="table" data-url="{{ route('schools.show', 1) }}"
                               data-click-to-select="true" data-side-pagination="server"
                               data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                               data-search="true" data-toolbar="#toolbar" data-show-columns="true"
                               data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true"
                               data-sort-name="id" data-sort-order="desc" data-maintain-selected="true" data-export-data-type='all'
                               data-export-options='{ "fileName": "{{__('school') }}-<?= date(' d-m-y') ?>" ,"ignoreColumn":["operate"]}'
                               data-show-export="true" data-query-params="schoolQueryParams" data-escape="true">
                            <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                <th scope="col" data-field="no">{{ __('no.') }}</th>
                                <th scope="col" data-field="code" data-visible="false">{{ __('code')}}</th>
                                <th scope="col" data-field="logo" data-formatter="imageFormatter">{{ __('logo') }}</th>
                                <th scope="col" data-field="name">{{ __('name') }}</th>
                                <th scope="col" data-field="support_email">{{__('school').' '.__('email')}}</th>
                                <th scope="col" data-field="support_phone">{{__('school').' '.__('phone')}}</th>
                                <th scope="col" data-field="tagline">{{ __('tagline') }}</th>
                                <th scope="col" data-field="address">{{ __('address') }}</th>
                                <th scope="col" data-field="admin_id" data-visible="false">{{ __('admin').' '.__('id')}}</th>
                                <th scope="col" data-field="user" data-formatter="schoolAdminFormatter">{{ __('school').' '.__('admin') }}</th>
                                <th scope="col" data-field="active_plan">{{ __('active_plan') }}</th>
                                <th scope="col" data-field="status" data-formatter="activeStatusFormatter">{{ __('status') }}</th>
                                <th scope="col" data-field="operate" data-formatter="actionColumnFormatter" data-events="schoolEvents" data-escape="false">{{ __('action') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- School Edit Model --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{__('edit')}} {{__('school')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <form id="edit-form" class="pt-3 edit-form" action="{{ url('schools') }}">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">

                        
                        <div class="form-group col-sm-12 col-md-6">
                            <label for="edit_school_name">{{ __('name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="edit_school_name" id="edit_school_name" placeholder="{{__('schools')}}" class="form-control" required>
                        </div>
                        <div class="form-group col-sm-12 col-md-6">
                            <label>{{ __('logo') }}</label>
                            <input type="file" id="edit_school_image" name="edit_school_image" class="file-upload-default" accept="image/png, image/jpg, image/jpeg, image/svg+xml"/>
                            <div class="input-group">
                                <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('logo') }}" aria-label=""/>
                                <span class="input-group-append">
                                    <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                </span>
                            </div>
                            <div style="width: 60px;">
                                <img src="" id="edit-school-logo-tag" class="img-fluid w-100" alt=""/>
                            </div>
                        </div>
                        <div class="form-group col-sm-12 col-md-3">
                            <label for="edit_school_support_email">{{ __('school').' '.__('email') }} <span class="text-danger">*</span></label>
                            <input type="email" name="edit_school_support_email" id="edit_school_support_email" placeholder="{{__('support').' '.__('email')}}" class="form-control" required>
                        </div>
                        <div class="form-group col-sm-12 col-md-3">
                            <label for="edit_school_support_phone">{{ __('school').' '.__('phone') }} <span class="text-danger">*</span></label>
                            <input type="number" name="edit_school_support_phone" min="0" id="edit_school_support_phone" placeholder="{{__('support').' '.__('phone')}}" class="form-control remove-number-increment" required>
                        </div>
                        
                        <div class="form-group col-sm-12 col-md-3">
                            <label for="edit_school_tagline">{{ __('tagline')}} <span class="text-danger">*</span></label>
                            <textarea name="edit_school_tagline" id="edit_school_tagline" cols="30" rows="3" class="form-control" placeholder="{{__('tagline')}}" required></textarea>
                        </div>
                        <div class="form-group col-sm-12 col-md-3">
                            <label for="edit_school_address">{{ __('address')}} <span class="text-danger">*</span></label>
                            <textarea name="edit_school_address" id="edit_school_address" cols="30" rows="3" class="form-control" placeholder="{{__('address')}}" required></textarea>
                        </div>

                        <div class="form-group col-sm-12 col-md-3">
                            <label for="school_domain">{{ __('domain')}}</label>
                            <div class="input-group mb-3">
                                    <input type="text" class="form-control domain-pattern" id="edit_domain" name="edit_domain" placeholder="{{ __('domain') }}" aria-label="Recipient's username" aria-describedby="basic-addon2">
                                <div class="input-group-append">
                                    <span class="input-group-text text-body" id="basic-addon2">.{{ $baseUrlWithoutScheme }}</span>
                                </div>
                            </div>
                        </div>



                        <div class="form-group col-sm-12 col-md-3">
                            <label for="assign_package">{{ __('assign_package')}} </label>
                            {!! Form::select('assign_package', $packages, null, ['class' => 'form-control mb-2', 'placeholder' => __('select_package'),'id' => 'edit_assign_package']) !!}
                            <span class="text-danger text-small">
                                {{ __('note') }}: {{ __('if_the_school_does_not_currently_have_a_plan_please_assign_from_here_If_there_is_already_an_active_plan_proceed_to_the_subscription_page_to_make_any_necessary_changes') }}.
                            </span>

                        </div>

                        <div class="form-group col-sm-12 col-md-3">
                            <label for="school_code">{{ __('school_code')}} </label>
                            <input type="text" name="code" disabled id="school_code" placeholder="{{__('school_code')}}" class="form-control" required>

                        </div>


                        

                    </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('close') }}</button>
                        <input class="btn btn-theme" type="submit" value={{ __('submit') }} />
                    </div>
                </form>
            </div>
        </div>
    </div>



    {{-- Manage Admin --}}
    <div class="modal fade" id="editAdminModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{__('change_admin')}}</h5>
                    <button type="button" class="close close-modal" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-close"></i></span>
                    </button>
                </div>
                <form id="admin-form-modal" class="create-form change-school-admin" action="{{ url('schools/admin/update') }}" data-success-function="successFunction" method="post" novalidate>
                    <input type="hidden" name="edit_id" id="edit_school_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-12">
                                <label>{{ __('admin') . ' ' . __('email') }} <span class="text-danger">*</span></label>
                                <input type="email" name="edit_admin_email" id="edit-admin-email" class="form-control">
                                <input type="hidden" id="edit_admin_id" name="edit_admin_id">
                                {{-- <select class="edit-school-admin-search w-100 form-control" aria-label=""></select> --}}
                                {{-- <input type="hidden" id="edit_admin_email" name="edit_admin_email"> --}}
                            </div>

                            <div class="form-group col-sm-12 col-md-6">
                                <label for="edit-admin-first-name">{{ __('admin') . ' ' . __('first_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="edit_admin_first_name" id="edit-admin-first-name" placeholder="{{__('admin') . ' ' . __('first_name')}}" class="form-control" required>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label for="edit-admin-last-name">{{ __('admin') . ' ' . __('last_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="edit_admin_last_name" id="edit-admin-last-name" placeholder="{{__('admin') . ' ' . __('last_name')}}" class="form-control" required>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label for="edit-admin-contact">{{ __('admin') . ' ' . __('contact') }} <span class="text-danger">*</span></label>
                                <input type="number" name="edit_admin_contact" id="edit-admin-contact" placeholder="{{__('admin') . ' ' . __('contact')}}" class="form-control remove-number-increment" min="0" required>
                            </div>
                            <div class="form-group col-sm-12 col-md-6">
                                <label>{{ __('admin') . ' ' . __('image') }}</label>
                                <input type="file" name="edit_admin_image" class="edit-admin-image file-upload-default" accept="image/png, image/jpg, image/jpeg, image/svg+xml"/>
                                <div class="input-group col-xs-12">
                                    <input type="text" class="form-control file-upload-info" disabled="" placeholder="{{ __('admin') . ' ' . __('image') }}" aria-label=""/>
                                    <span class="input-group-append">
                                    <button class="file-upload-browse btn btn-theme" id="file-upload-admin-browse" type="button">{{ __('upload') }}</button>
                                </span>
                                </div>
                                <div style="width: 100px;">
                                    <img src="" id="admin-image-tag" class="img-fluid w-100" alt=""/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-12 col-md-4">
                                <div class="d-flex">
                                    <div class="form-check w-fit-content">
                                        <label class="form-check-label ml-4">
                                            <input type="checkbox" class="form-check-input" name="reset_password" value="1">{{ __('reset_password') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary close-modal" data-dismiss="modal">{{ __('close') }}</button>
                        <input class="btn btn-theme" type="submit" value={{ __('submit') }} />
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('js')
    <script>
        function successFunction() {
            $('#editAdminModal').modal('hide');
        }
    </script>
@endsection

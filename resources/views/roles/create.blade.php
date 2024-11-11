@extends('layouts.master')

@section('title')
    {{ __('Create New Role') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('Create New Role') }}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-end">
                            <a class="btn btn-sm btn-theme" href="{{ route('roles.index') }}"> {{ __('back') }}</a>
                        </div>

                        <div class="row">
                            {!! Form::open(['route' => 'roles.store', 'method' => 'POST']) !!}
                            <div class="row">
                                <div class="col-xs-12 col-sm-12 col-md-12">
                                    <div class="form-group">
                                        <label>{{ __('name') }} <span class="text-danger">*</span></label>
                                        {!! Form::text('name', null, ['placeholder' => 'Name', 'class' => 'form-control']) !!}
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-12">
                                    <label><strong>{{ __('permission') }}:</strong></label>
                                    <div class="row">
                                        @foreach ($permission as $value)
                                            <div class="form-group col-lg-3 col-sm-12 col-xs-12 col-md-3">
                                                <div class="form-check">
                                                    <label class="form-check-label">
                                                        {{ Form::checkbox('permission[]', $value->id, false, ['class' => 'name form-check-input']) }}
                                                        {{ $value->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-12">
                                    {{-- <button type="submit" class="btn btn-theme float-right">{{ __('submit') }}</button> --}}
                                    <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit" value={{ __('submit') }}>
                                    <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.master')

@section('title')
    {{ __('database_backup') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_backup') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin">
                <div class="card">
                    <div class="custom-card-body">
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button class="btn create-backup btn-theme btn-sm">{{ __('create_backup') }}</button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list_backups') }}
                        </h4>
                        
                        <table aria-describedby="mydesc" class='table' id='table_list'
                               data-toggle="table" data-url="{{ url('database-backup/show') }}"
                               data-click-to-select="true" data-side-pagination="server"
                               data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                               data-search="true" data-toolbar="#toolbar" data-show-columns="true"
                               data-show-refresh="true" data-fixed-columns="false" data-fixed-number="2"
                               data-fixed-right-number="1" data-trim-on-search="false"
                               data-mobile-responsive="true" data-sort-name="id"
                               data-sort-order="desc" data-maintain-selected="true"
                               data-query-params="queryParams" data-show-export="true"
                               data-export-options='{"fileName": "section-list-<?= date('d-m-y') ?>","ignoreColumn": ["operate"]}'
                               data-escape="true">
                            <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{__('id')}}</th>
                                <th scope="col" data-field="no">{{__('no.')}}</th>
                                <th scope="col" data-field="name">{{__('name')}}</th>
                                <th scope="col" data-field="created_at" data-formatter="dateTimeFormatter" data-sortable="true" data-visible="false">{{__('created_at')}}</th>
                                <th scope="col" data-field="updated_at" data-formatter="dateTimeFormatter" data-sortable="true" data-visible="false">{{__('updated_at')}}</th>
                                <th scope="col" data-field="operate" data-events="sectionEvents" data-escape="false">{{__('action')}}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

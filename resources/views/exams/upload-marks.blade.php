@extends('layouts.master')

@section('title')
    {{ __('exam_marks') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage') . ' ' . __('exam_marks') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('create') . ' ' . __('exam_marks') }}
                        </h4>
                        <form action="{{ route('exams.submit-marks') }}" class="create-form" id="formdata" data-success-function="formSuccessFunction">
                            @csrf
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="">{{ __('class_section') }}</label>
                                    <select name="" id="exam-class-section-id" required class="form-control">
                                        <option value="">-- {{ __('select_class_section') }} --</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}" data-classId="{{ $class->class_id }}">{{ $class->full_name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    @if(isset($exams))
                                        <label for="">{{ __('exam') }}</label>
                                        <select required name="exam_id" id="exam-id" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                            <option value="">{{ __('select') . ' ' . __('exam') }}</option>
                                            <option value="data-not-found">-- {{ __('no_data_found') }} --</option>
                                            @foreach ($exams as $data)
                                                <option value="{{ $data->id }}" data-classId="{{$data->class_id}}"> {{ $data->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <label for="">{{ __('exam') }}</label>
                                        <select required name="exam_id" id="exam_id" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                            <option value="">--- {{ __('no_data_found') }} ---</option>
                                        </select>
                                    @endif
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="">{{ __('subject') }}</label>
                                    <select name="class_subject_id" required id="class_subject_id" class="form-control">
                                        <option value="">-- {{ __('Select Subject') }} --</option>
                                        <option value="data-not-found">-- {{ __('no_data_found') }} --</option>
                                        @foreach ($exams as $exam)
                                            @foreach($exam->timetable as $data)
                                                @if ($data->class_subject)
                                                    @foreach ($data->class_subject->subject_teacher as $subject)
                                                        <option value="{{ $subject->class_subject_id }}" data-exam-id="{{ $data->exam_id }}" data-class-section-id="{{ $subject->class_section_id }}">{{ $subject->subject_with_name}}</option>
                                                        
                                                    @endforeach  
                                            @endif
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-sm-12 col-md-12">
                                    <button type="button" id="search" class="btn btn-theme">{{ __('search') }}</button>
                                </div>
                            </div>
                            <div class="show_student_list">
                                <table aria-describedby="mydesc" class='table student_table' id='table_list'
                                       data-toggle="table" data-url="{{ route('exams.marks-list') }}"
                                       data-click-to-select="true" data-side-pagination="server"
                                       data-pagination="false" data-page-list="[5, 10, 20, 50, 100, 200]"
                                       data-search="true" data-show-columns="true" data-show-refresh="true"
                                       data-fixed-columns="false" data-fixed-number="2"
                                       data-fixed-right-number="1" data-trim-on-search="false"
                                       data-mobile-responsive="true" data-sort-name="id"
                                       data-sort-order="desc" data-maintain-selected="true" data-show-export="true"
                                       data-export-data-type='all' data-export-options='{ "fileName": "exam-result-list-<?= date(' d-m-y') ?>" ,"ignoreColumn": ["operate"]}'
                                       data-query-params="uploadMarksqueryParams" data-toolbar="#toolbar" data-escape="true">
                                    <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">{{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="student_name" data-sortable="false" data-formatter="examStudentNameFormatter">{{ __('name') }}</th>
                                        <th scope="col" data-field="total_marks">{{ __('total_marks') }}</th>
                                        <th scope="col" data-width="500" data-field="obtained_marks" data-formatter="obtainedMarksFormatter">{{ __('obtained_marks') }}</th>
                                        {{-- <th scope="col" data-field="teacher_review"  data-formatter="teacherReviewFormatter">{{ __('teacher_review') }}</th> --}}
                                    </tr>
                                    </thead>
                                </table>
                                <div class="form-group mt-3">
                                    <input class="btn btn-theme float-right ml-3" id="create-btn-result" type="submit" value={{ __('submit') }}>
                                <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
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
        $('#search').on('click , input', function () {
            $('.show_student_list').show();
            $('.student_table').bootstrapTable('refresh');
        });

        function formSuccessFunction(response) {
            setTimeout(() => {
                $('.student_table').bootstrapTable('refresh');
            }, 500);
        }

    </script>
@endsection

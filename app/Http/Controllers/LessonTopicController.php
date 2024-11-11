<?php

namespace App\Http\Controllers;

use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\Files\FilesInterface;
use App\Repositories\Lessons\LessonsInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\Topics\TopicsInterface;
use App\Rules\DynamicMimes;
use App\Rules\uniqueTopicInLesson;
use App\Rules\YouTubeUrl;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LessonTopicController extends Controller {
    private LessonsInterface $lesson;
    private TopicsInterface $topic;
    private FilesInterface $files;
    private ClassSectionInterface $classSection;
    private SubjectTeacherInterface $subjectTeacher;

    public function __construct(LessonsInterface $lesson, TopicsInterface $topic, FilesInterface $files, ClassSectionInterface $classSection, SubjectTeacherInterface $subjectTeacher) {
        $this->lesson = $lesson;
        $this->topic = $topic;
        $this->files = $files;
        $this->classSection = $classSection;
        $this->subjectTeacher = $subjectTeacher;
    }

    public function index() {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('topic-list');
        $class_section = $this->classSection->builder()->with('class', 'class.stream', 'section', 'medium')->get();
        $subjectTeachers = $this->subjectTeacher->builder()->with('subject:id,name,type')->get();
        $lessons = $this->lesson->builder()->get();
        return response(view('lessons.topic', compact('class_section', 'subjectTeachers', 'lessons')));
    }


    public function store(Request $request) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('topic-create');
        $validator = Validator::make($request->all(), [
            'class_section_id'      => 'required|numeric',
            'class_subject_id'      => 'required|numeric',
            'lesson_id'             => 'required|numeric',
            'name'                  => ['required', new uniqueTopicInLesson($request->lesson_id)],
            'description'           => 'required',
            'file_data'             => 'nullable|array',
            'file_data.*.type'      => 'required|in:file_upload,youtube_link,video_upload,other_link',
            'file_data.*.name'      => 'required_with:file_data.*.type',
            'file_data.*.thumbnail' => 'required_if:file_data.*.type,youtube_link,video_upload,other_link',
            'file_data.*.link'      => ['nullable', 'required_if:file_data.*.type,youtube_link', new YouTubeUrl], //Regex for YouTube Link
            'file_data.*.file'      => ['nullable', 'required_if:file_data.*.type,file_upload,video_upload', new DynamicMimes],
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $topic = $this->topic->create($request->all());

            if (!empty($request->file_data)) {
                // Initialize the Empty Array
                $topicFileData = array();

                // Create A File Model Instance
                $topicFile = $this->files->model();

                // Get the Association Values of File with Topic
                $topicModelAssociate = $topicFile->modal()->associate($topic);

                // Loop to the File Array from Request
                foreach ($request->file_data as $file) {

                    // Initialize of Empty Array
                    //                    $tempFileData = array();

                    // Check the File type Exists
                    if ($file['type']) {

                        // Make custom Array for storing the data in TempFileData
                        $tempFileData = array(
                            'modal_type' => $topicModelAssociate->modal_type,
                            'modal_id'   => $topicModelAssociate->modal_id,
                            'file_name'  => $file['name'],
                        );

                        // If File Upload
                        if ($file['type'] == "file_upload") {

                            // Add Type And File Url to TempDataArray and make Thumbnail data null
                            $tempFileData['type'] = 1;
                            $tempFileData['file_thumbnail'] = null;
                            $tempFileData['file_url'] = $file['file'];
                        } elseif ($file['type'] == "youtube_link") {

                            // Add Type , Thumbnail and Link to TempDataArray
                            $tempFileData['type'] = 2;
                            $tempFileData['file_thumbnail'] = $file['thumbnail'];
                            $tempFileData['file_url'] = $file['link'];
                        } elseif ($file['type'] == "video_upload") {

                            // Add Type , File Thumbnail and File URL to TempDataArray
                            $tempFileData['type'] = 3;
                            $tempFileData['file_thumbnail'] = $file['thumbnail'];
                            $tempFileData['file_url'] = $file['file'];
                        } elseif ($file['type'] == "other_link") {

                            // Add Type , File Thumbnail and File URL to TempDataArray
                            $tempFileData['type'] = 4;
                            $tempFileData['file_thumbnail'] = $file['thumbnail'];
                            $tempFileData['file_url'] = $file['link'];
                        }

                        // Store to Multi Dimensional topicFileData Array
                        $topicFileData[] = $tempFileData;
                    }
                }
                // Store Bulk Data of Files
                $this->files->createBulk($topicFileData);
            }

            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Topic Controller -> Store Method");
            ResponseService::errorResponse();
        }
    }

    public function show() {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('topic-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = $this->topic->builder()
            ->has('lesson')
            ->with('lesson.class_section','lesson.class_subject','file','lesson.class_subject.subject')
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('name', 'LIKE', "%$search%")
                        ->orwhere('description', 'LIKE', "%$search%")
                        ->orWhereHas('lesson.class_section.section', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('lesson.class_section.class', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('lesson.class_subject.subject', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('lesson', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                });
                });
            })
            ->when(request('class_subject_id') != null, function ($query) {
                $class_subject_id = request('class_subject_id');
                $query->where(function ($query) use ($class_subject_id) {
                    $query->whereHas('lesson', function ($q) use ($class_subject_id) {
                        $q->where('class_subject_id', $class_subject_id);
                    });
                });
            })
            ->when(request('class_id') != null, function ($query) {
                $class_id = request('class_id');
                $query->where(function ($query) use ($class_id) {
                    $query->whereHas('lesson', function ($q) use ($class_id) {
                        $q->where('class_section_id', $class_id);
                    });
                });
            })
            ->when(request('lesson_id') != null, function ($query) {
                $lesson_id = request('lesson_id');
                $query->where(function ($query) use ($lesson_id) {
                    $query->where('lesson_id', $lesson_id);
                });
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {

            $row = (object)$row;

            // $operate = BootstrapTableService::button(route('lesson-topic.edit', $row->id), ['btn-gradient-primary'], ['title' => 'Edit'], ['fa fa-edit']);
            $operate = BootstrapTableService::button('fa fa-edit', route('lesson-topic.edit', $row->id), ['btn-gradient-primary'], ['title' => 'Edit']);
            $operate .= BootstrapTableService::deleteButton(route('lesson-topic.destroy', $row->id));

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('topic-edit');
        $class_section = $this->classSection->builder()->with('class', 'class.stream', 'section', 'medium')->get();
        $subjectTeachers = $this->subjectTeacher->builder()->with('subject:id,name,type')->get();
        $lessons = $this->lesson->builder()->get();
        $topic = $this->topic->builder()->with('file')->where('id', $id)->first();

        return response(view('lessons.edit_topic', compact('class_section', 'subjectTeachers', 'lessons', 'topic')));
    }

    public function update($id, Request $request) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('topic-edit');
        $validator = Validator::make(
            $request->all(),
            [
                'class_section_id' => 'required|numeric',
                'class_subject_id' => 'required|numeric',
                'lesson_id'        => 'required|numeric',
                'name'             => ['required', new uniqueTopicInLesson($request->lesson_id, $id)],
                'description'      => 'required',
                'file_data'        => 'nullable|array',
                'file_data.*.type' => 'required|in:file_upload,youtube_link,video_upload,other_link',
                'file_data.*.name' => 'required_with:file_data.*.type',
                'file_data.*.link' => ['nullable', 'required_if:file_data.*.type,youtube_link', new YouTubeUrl], //Regex for YouTube Link
                'file_data.*.file' => ['nullable', new DynamicMimes],
            ],
            [
                'name.unique' => trans('topic_already_exists')
            ]
        );
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $topic = $this->topic->update($id, $request->all());

            //Add the new Files
            if ($request->file_data) {

                foreach ($request->file_data as $file) {
                    if ($file['type']) {

                        // Create A File Model Instance
                        $topicFile = $this->files->model();

                        // Get the Association Values of File with Topic
                        $topicModelAssociate = $topicFile->modal()->associate($topic);

                        // Make custom Array for storing the data in fileData
                        $fileData = array(
                            'id'         => $file['id'] ?? null,
                            'modal_type' => $topicModelAssociate->modal_type,
                            'modal_id'   => $topicModelAssociate->modal_id,
                            'file_name'  => $file['name'],
                        );

                        // If File Upload
                        if ($file['type'] == "file_upload") {

                            // Add Type And File Url to TempDataArray and make Thumbnail data null
                            $fileData['type'] = 1;
                            $fileData['file_thumbnail'] = null;
                            if (!empty($file['file'])) {
                                $fileData['file_url'] = $file['file'];
                            }
                        } elseif ($file['type'] == "youtube_link") {

                            // Add Type , Thumbnail and Link to TempDataArray
                            $fileData['type'] = 2;
                            if (!empty($file['thumbnail'])) {
                                $fileData['file_thumbnail'] = $file['thumbnail'];
                            }
                            $fileData['file_url'] = $file['link'];
                        } elseif ($file['type'] == "video_upload") {

                            // Add Type , File Thumbnail and File URL to TempDataArray
                            $fileData['type'] = 3;
                            if (!empty($file['thumbnail'])) {
                                $fileData['file_thumbnail'] = $file['thumbnail'];
                            }
                            if (!empty($file['file'])) {
                                $fileData['file_url'] = $file['file'];
                            }
                        } elseif ($file['type'] == "other_link") {

                            // Add Type , File Thumbnail and File URL to TempDataArray
                            $fileData['type'] = 4;
                            if ($file['thumbnail']) {
                                $fileData['file_thumbnail'] = $file['thumbnail'];
                            }
                            $fileData['file_url'] = $file['link'];
                        }
                        $fileData['created_at'] = date('Y-m-d H:i:s');
                        $fileData['updated_at'] = date('Y-m-d H:i:s');

                        $this->files->updateOrCreate(['id' => $file['id']], $fileData);
                    }
                }
            }

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Topic Controller -> Update Method");
            ResponseService::errorResponse();
        }
    }


    public function destroy($id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-delete');
        try {
            DB::beginTransaction();
            $this->topic->deleteById($id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Topic Controller -> Delete Method");
            ResponseService::errorResponse();
        }
    }

    public function restore(int $id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-delete');
        try {
            $this->topic->findOnlyTrashedById($id)->restore();
            ResponseService::successResponse("Data Restored Successfully");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function trash($id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-delete');
        try {
            $this->topic->findOnlyTrashedById($id)->forceDelete();
            ResponseService::successResponse("Data Deleted Permanently");
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
}

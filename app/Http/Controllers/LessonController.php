<?php

namespace App\Http\Controllers;

use App\Models\LessonTopic;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\Files\FilesInterface;
use App\Repositories\Lessons\LessonsInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Rules\DynamicMimes;
use App\Rules\uniqueLessonInClass;
use App\Rules\YouTubeUrl;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LessonController extends Controller {

    private SubjectTeacherInterface $subjectTeacher;
    private ClassSectionInterface $classSection;
    private LessonsInterface $lesson;
    private FilesInterface $files;
    private CachingService $cache;

    public function __construct(ClassSectionInterface $classSection, LessonsInterface $lesson, FilesInterface $files, SubjectTeacherInterface $subjectTeacher,CachingService $cache) {
        $this->subjectTeacher = $subjectTeacher;
        $this->classSection = $classSection;
        $this->lesson = $lesson;
        $this->files = $files;
        $this->cache = $cache;
    }

    public function index() {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('lesson-list');
        $class_section = $this->classSection->builder()->with('class', 'class.stream', 'section', 'medium')->get();
        $subjectTeachers = $this->subjectTeacher->builder()->with('subject:id,name,type')->get();
        $lessons = $this->lesson->builder()->get();
        return response(view('lessons.index', compact('class_section', 'subjectTeachers', 'lessons')));
    }

    public function store(Request $request) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('lesson-create');
        $validator = Validator::make(
            $request->all(),
            [
                'name'                  => ['required', new uniqueLessonInClass($request->class_section_id, $request->class_subject_id)],
                'description'           => 'required',
                'class_section_id'      => 'required|numeric',
                'class_subject_id'      => 'required|numeric',
                'file_data'             => 'nullable|array',
                'file_data.*.type'      => 'required|in:file_upload,youtube_link,video_upload,other_link',
                'file_data.*.name'      => 'required_with:file_data.*.type',
                'file_data.*.thumbnail' => 'required_if:file_data.*.type,youtube_link,video_upload,other_link',
                'file_data.*.link'      => ['nullable', 'required_if:file_data.*.type,youtube_link', new YouTubeUrl], //Regex for YouTube Link
                'file_data.*.file'      => ['nullable', 'required_if:file_data.*.type,file_upload,video_upload', new DynamicMimes],
            ],
            [
                'name.unique' => trans('lesson_already_exists')
            ]
        );

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $lessonData = array(
                ...$request->all(),
            );

            // Store Lesson Data
            $lesson = $this->lesson->create($lessonData);

            if (!empty($request->file_data)) {
                // Initialize the Empty Array
                $lessonFileData = array();

                // Create A File Model Instance
                $lessonFile = $this->files->model();

                // Get the Association Values of File with Lesson
                $lessonModelAssociate = $lessonFile->modal()->associate($lesson);

                // Loop to the File Array from Request
                foreach ($request->file_data as $file) {

                    // Initialize of Empty Array
                    //                    $tempFileData = array();

                    // Check the File type Exists
                    if ($file['type']) {

                        // Make custom Array for storing the data in TempFileData
                        $tempFileData = array(
                            'modal_type' => $lessonModelAssociate->modal_type,
                            'modal_id'   => $lessonModelAssociate->modal_id,
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

                        // Store to Multi Dimensional LessonFileData Array
                        $lessonFileData[] = $tempFileData;
                    }
                }
                // Store Bulk Data of Files
                $this->files->createBulk($lessonFileData);
            }

            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Controller -> Store Method");
            ResponseService::errorResponse();
        }
    }

    public function show() {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('lesson-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = $this->lesson->builder()->with('class_subject', 'class_section', 'topic', 'file')
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('name', 'LIKE', "%$search%")
                        ->orwhere('description', 'LIKE', "%$search%")
                        ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orWhereHas('class_section.section', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('class_section.class', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('class_subject.subject', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                });
                });
            })
            ->when(request('class_id') != null, function ($query) {
                $class_id = request('class_id');
                $query->where(function ($query) use ($class_id) {
                    $query->where('class_section_id', $class_id);
                });
            })
            ->when(request('class_subject_id') != null, function ($query) {
                $subject_id = request('class_subject_id');
                $query->where(function ($query) use ($subject_id) {
                    $query->where('class_subject_id', $subject_id);
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

            // $operate = BootstrapTableService::button(route('lesson.edit', $row->id), ['btn-gradient-primary'], ['title' => 'Edit'], ['fa fa-edit']);
            $operate = BootstrapTableService::button('fa fa-edit', route('lesson.edit', $row->id), ['btn-gradient-primary'], ['title' => 'Edit']);
            $operate .= BootstrapTableService::deleteButton(route('lesson.destroy', $row->id));

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
        ResponseService::noPermissionThenRedirect('lesson-edit');
        $class_section = $this->classSection->builder()->with('class', 'class.stream', 'section', 'medium')->get();
        $subjectTeachers = $this->subjectTeacher->builder()->with('subject:id,name,type')->get();
        $lessonsList = $this->lesson->all();
        $lesson = $this->lesson->builder()->with('file')->where('id', $id)->first();

        return response(view('lessons.edit_lesson', compact('class_section', 'subjectTeachers', 'lessonsList', 'lesson')));
    }

    public function update(Request $request, $id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenSendJson('lesson-edit');
        $validator = Validator::make(
            $request->all(),
            [
                'name'             => ['required', new uniqueLessonInClass($request->class_section_id, $request->subject_id, $id)],
                'description'      => 'required',
                'class_section_id' => 'required|numeric',
                'class_subject_id' => 'required|numeric',
                'file_data'        => 'nullable|array',
                'file_data.*.type' => 'required|in:file_upload,youtube_link,video_upload,other_link',
                'file_data.*.name' => 'required_with:file_data.*.type',
                'file_data.*.link' => ['nullable', 'required_if:file_data.*.type,youtube_link', new YouTubeUrl], //Regex for YouTube Link
                'file_data.*.file' => ['nullable', new DynamicMimes],
            ],
            [
                'name.unique' => trans('lesson_already_exists')
            ]
        );
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $lesson = $this->lesson->update($id, $request->all());

            //Add the new Files
            if ($request->file_data) {
                // Initialize the Empty Array
                //                $lessonFileData = array();

                foreach ($request->file_data as $file) {
                    if ($file['type']) {

                        // Create A File Model Instance
                        $lessonFile = $this->files->model();

                        // Get the Association Values of File with Lesson
                        $lessonModelAssociate = $lessonFile->modal()->associate($lesson);

                        // Make custom Array for storing the data in TempFileData
                        $tempFileData = array(
                            'id'         => $file['id'] ?? null,
                            'modal_type' => $lessonModelAssociate->modal_type,
                            'modal_id'   => $lessonModelAssociate->modal_id,
                            'file_name'  => $file['name'],
                        );

                        // If File Upload
                        if ($file['type'] == "file_upload") {

                            // Add Type And File Url to TempDataArray and make Thumbnail data null
                            $tempFileData['type'] = 1;
                            $tempFileData['file_thumbnail'] = null;
                            if (!empty($file['file'])) {
                                $tempFileData['file_url'] = $file['file'];
                            }
                        } elseif ($file['type'] == "youtube_link") {

                            // Add Type , Thumbnail and Link to TempDataArray
                            $tempFileData['type'] = 2;
                            if (!empty($file['thumbnail'])) {
                                $tempFileData['file_thumbnail'] = $file['thumbnail'];
                            }
                            $tempFileData['file_url'] = $file['link'];
                        } elseif ($file['type'] == "video_upload") {

                            // Add Type , File Thumbnail and File URL to TempDataArray
                            $tempFileData['type'] = 3;
                            if (!empty($file['thumbnail'])) {
                                $tempFileData['file_thumbnail'] = $file['thumbnail'];
                            }
                            if (!empty($file['file'])) {
                                $tempFileData['file_url'] = $file['file'];
                            }
                        } elseif ($file['type'] == "other_link") {

                            // Add Type , File Thumbnail and File URL to TempDataArray
                            $tempFileData['type'] = 4;
                            if ($file['thumbnail']) {
                                $tempFileData['file_thumbnail'] = $file['thumbnail'];
                            }
                            $tempFileData['file_url'] = $file['link'];
                        }
                        $tempFileData['created_at'] = date('Y-m-d H:i:s');
                        $tempFileData['updated_at'] = date('Y-m-d H:i:s');

                        $this->files->updateOrCreate(['id' => $file['id']], $tempFileData);
                    }
                }
            }
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Controller -> Update Method");
            ResponseService::errorResponse();
        }
    }

    public function destroy($id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenSendJson('lesson-delete');
        try {

            $lesson_topics = LessonTopic::where('lesson_id', $id)->count();
            if ($lesson_topics) {
                $response = array('error' => true, 'message' => trans('cannot_delete_because_data_is_associated_with_other_data'));
            } else {

                // Find the Data By ID
                $lesson = $this->lesson->findById($id);

                // If File exists
                if ($lesson->file) {

                    // Loop on the Files
                    foreach ($lesson->file as $file) {

                        // Remove the Files From the Local
                        if (Storage::disk('public')->exists($file->file_url)) {
                            Storage::disk('public')->delete($file->file_url);
                        }
                    }
                }

                // Delete File Data
                $lesson->file()->delete();

                // Delete Lesson Data
                $lesson->delete();

                ResponseService::successResponse('Data Deleted Successfully');
            }
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Controller -> Destroy method");
            ResponseService::errorResponse();
        }
        return response()->json($response);
    }


    public function search(Request $request) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noPermissionThenRedirect('lesson-list');
        try {
            // Get the new Instance of Lesson Model
            $lesson = $this->lesson->model();

            if (isset($request->subject_id)) {
                $lesson = $lesson->where('subject_id', $request->subject_id);
            }

            if (isset($request->class_section_id)) {
                $lesson = $lesson->where('class_section_id', $request->class_section_id);
            }

            $lesson = $lesson->get();

            $response = array(
                'error'   => false,
                'data'    => $lesson,
                'message' => 'Lesson fetched successfully'
            );
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Lesson Controller -> Search Method");
            ResponseService::errorResponse();
        }
        return response()->json($response);
    }

    public function deleteFile($id) {
        ResponseService::noFeatureThenRedirect('Lesson Management');
        ResponseService::noAnyPermissionThenRedirect(['lesson-delete', 'topic-delete']);
        try {
            DB::beginTransaction();

            // Find the Data by FindByID
            $file = $this->files->findById($id);

            // Delete the file data
            $file->delete();

            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Lesson Controller -> deleteFile Method");
            ResponseService::errorResponse();
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\File;
use App\Models\School;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Repositories\Announcement\AnnouncementInterface;
use App\Repositories\AnnouncementClass\AnnouncementClassInterface;
use App\Repositories\Assignment\AssignmentInterface;
use App\Repositories\AssignmentSubmission\AssignmentSubmissionInterface;
use App\Repositories\Attendance\AttendanceInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\ClassSubject\ClassSubjectInterface;
use App\Repositories\ClassTeachers\ClassTeachersInterface;
use App\Repositories\Exam\ExamInterface;
use App\Repositories\ExamMarks\ExamMarksInterface;
use App\Repositories\ExamResult\ExamResultInterface;
use App\Repositories\ExamTimetable\ExamTimetableInterface;
use App\Repositories\Files\FilesInterface;
use App\Repositories\Grades\GradesInterface;
use App\Repositories\Holiday\HolidayInterface;
use App\Repositories\Lessons\LessonsInterface;
use App\Repositories\Student\StudentInterface;
use App\Repositories\StudentSubject\StudentSubjectInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\Timetable\TimetableInterface;
use App\Repositories\Topics\TopicsInterface;
use App\Repositories\User\UserInterface;
use App\Rules\uniqueLessonInClass;
use App\Rules\uniqueTopicInLesson;
use App\Services\CachingService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use App\Rules\YouTubeUrl;
use App\Rules\DynamicMimes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use PDF;
use Str;

//use App\Models\Parents;

class TeacherApiController extends Controller
{

    private StudentInterface $student;
    private AttendanceInterface $attendance;
    private TimetableInterface $timetable;
    private AssignmentInterface $assignment;
    private AssignmentSubmissionInterface $assignmentSubmission;
    private CachingService $cache;
    private ClassSubjectInterface $classSubject;
    private FilesInterface $files;
    private LessonsInterface $lesson;
    private TopicsInterface $topic;
    private AnnouncementInterface $announcement;
    private AnnouncementClassInterface $announcementClass;
    private SubjectTeacherInterface $subjectTeacher;
    private StudentSubjectInterface $studentSubject;
    private HolidayInterface $holiday;
    private ExamInterface $exam;
    private ExamTimetableInterface $examTimetable;
    private ExamMarksInterface $examMarks;
    private UserInterface $user;
    private ClassSectionInterface $classSection;
    private ClassTeachersInterface $classTeacher;

    public function __construct(StudentInterface $student, AttendanceInterface $attendance, TimetableInterface $timetable, AssignmentInterface $assignment, AssignmentSubmissionInterface $assignmentSubmission, CachingService $cache, ClassSubjectInterface $classSubject, FilesInterface $files, LessonsInterface $lesson, TopicsInterface $topic, AnnouncementInterface $announcement, AnnouncementClassInterface $announcementClass, SubjectTeacherInterface $subjectTeacher, StudentSubjectInterface $studentSubject, HolidayInterface $holiday, ExamInterface $exam, ExamTimetableInterface $examTimetable, ExamMarksInterface $examMarks, UserInterface $user, ClassSectionInterface $classSection, ClassTeachersInterface $classTeacher)
    {
        $this->student = $student;
        $this->attendance = $attendance;
        $this->timetable = $timetable;
        $this->assignment = $assignment;
        $this->assignmentSubmission = $assignmentSubmission;
        $this->cache = $cache;
        $this->classSubject = $classSubject;
        $this->files = $files;
        $this->lesson = $lesson;
        $this->topic = $topic;
        $this->announcement = $announcement;
        $this->announcementClass = $announcementClass;
        $this->subjectTeacher = $subjectTeacher;
        $this->studentSubject = $studentSubject;
        $this->holiday = $holiday;
        $this->exam = $exam;
        $this->examTimetable = $examTimetable;
        $this->examMarks = $examMarks;
        $this->user = $user;
        $this->classSection = $classSection;
        $this->classTeacher = $classTeacher;
    }

    #[NoReturn] public function login(Request $request)
    {

        $validator = Validator::make($request->all(), ['school_code' => 'required',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $school = School::on('mysql')->where('code',$request->school_code)->first();

        if ($school) {
            DB::setDefaultConnection('school');
            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');
        } else {
            return response()->json(['message' => 'Invalid school code'], 400);
        }

        $user = User::withTrashed()
        ->where('email', $request->email)
        ->first();

        if ($user && Hash::check($request->password, $user->password)) {
            if ($user->trashed()) {
                // User is soft-deleted, handle accordingly
                ResponseService::errorResponse(trans('your_account_has_been_deactivated_please_contact_admin'), null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
            }
        }

        if (Auth::attempt([
            'email'    => $request->email,
            'password' => $request->password
        ])) {

            $auth = Auth::user();
            // $permission = $auth;
            if ($request->fcm_id) {
                $auth->fcm_id = $request->fcm_id;
                $auth->save();
            }
            // Check school status is activated or not
            if ($auth->school->status == 0 || $auth->status == 0) {
                $auth->fcm_id = '';
                $auth->save();
                ResponseService::errorResponse(trans('your_account_has_been_deactivated_please_contact_admin'), null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
            }

            $token = $auth->createToken($auth->first_name)->plainTextToken;
            if (Auth::user()->hasRole('Teacher')) {
                $user = $auth->load(['teacher','teacher.staffSalary.payrollSetting']);
            } else {
                $user = $auth->load(['staff','staff.staffSalary.payrollSetting']);
            }
            ResponseService::successResponse('User logged-in!', $user, ['token' => $token], config('constants.RESPONSE_CODE.LOGIN_SUCCESS'));
        }

        ResponseService::errorResponse('Invalid Login Credentials', null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
    }

    public function subjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'nullable|numeric',
            'subject_id'       => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {

            $subjects = $this->subjectTeacher->builder()->with('subject:id,name,type','class_section');

            $user = $request->user();
            $teacher = $user->teacher;
            $subjects = $teacher->subjects();
            if ($request->class_section_id) {
                $subjects = $subjects->where('class_section_id', $request->class_section_id);
            }

            if ($request->subject_id) {
                $subjects = $subjects->where('subject_id', $request->subject_id);
            }
            $subjects = $subjects->with('subject', 'class_section')->get();
            ResponseService::successResponse('Teacher Subject Fetched Successfully.', $subjects);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function getAssignment(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-list');
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'nullable|numeric',
            'class_subject_id'       => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = $this->assignment->builder()->with('class_section.class.stream', 'file', 'class_subject', 'class_section.medium');
            if ($request->class_section_id) {
                $sql = $sql->where('class_section_id', $request->class_section_id);
            }

            if ($request->class_subject_id) {
                $sql = $sql->where('class_subject_id', $request->class_subject_id);
            }



            $data = $sql->orderBy('id', 'DESC')->paginate();
            ResponseService::successResponse('Assignment Fetched Successfully.', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function createAssignment(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-create');
        $validator = Validator::make($request->all(), [
            "class_section_id"            => 'required|numeric',
            "class_subject_id"            => 'required|numeric',
            "name"                        => 'required',
            "instructions"                => 'nullable',
            "due_date"                    => 'required|date',
            "points"                      => 'nullable',
            "resubmission"                => 'nullable|boolean',
            "extra_days_for_resubmission" => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear();

            $assignmentData = array(
                ...$request->all(),
                'due_date'                    => date('Y-m-d H:i', strtotime($request->due_date)),
                'resubmission'                => $request->resubmission ? 1 : 0,
                'extra_days_for_resubmission' => $request->resubmission ? $request->extra_days_for_resubmission : null,
                'session_year_id'             => $sessionYear->id,
                'created_by'                  => Auth::user()->id,
            );
            $assignment = $this->assignment->create($assignmentData);

            $subject_name = $this->classSubject->findById($request->class_subject_id, ['id', 'subject_id'], ['subject'])->subject_with_name;
            $title = 'New assignment added in ' . $subject_name;
            $body = $request->name;
            $type = "assignment";

            $user = $this->student->builder()->select('user_id')->where('class_section_id', $request->class_section_id)->get()->pluck('user_id');

            send_notification($user, $title, $body, $type);

            if ($request->hasFile('file')) {
                $assignmentModelAssociate = $this->files->model()->modal()->associate($assignment);
                foreach ($request->file as $file_upload) {
                    $tempFileData = array(
                        'modal_type' => $assignmentModelAssociate->modal_type,
                        'modal_id'   => $assignmentModelAssociate->modal_id,
                        'file_name'  => $file_upload->getClientOriginalName(),
                        'type'       => 1,
                        'file_url'   => $file_upload
                    );
                    $fileData[] = $tempFileData; // Store Temp File Data in Multi-Dimensional File Data Array
                }
                $this->files->createBulk($fileData); // Store File Data
            }
            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), [
                'does not exist','file_get_contents'
            ])) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function updateAssignment(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-edit');
        $validator = Validator::make($request->all(), [
            "assignment_id"               => 'required|numeric',
            "class_section_id"            => 'required|numeric',
            "class_subject_id"                  => 'required|numeric',
            "name"                        => 'required',
            "instructions"                => 'nullable',
            "due_date"                    => 'required|date',
            "points"                      => 'nullable',
            "resubmission"                => 'nullable|boolean',
            "extra_days_for_resubmission" => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear();

            $sessionYear = $this->cache->getDefaultSessionYear();

            $assignmentData = array(
                ...$request->all(),
                'due_date'                    => date('Y-m-d H:i', strtotime($request->due_date)),
                'resubmission'                => $request->resubmission ? 1 : 0,
                'extra_days_for_resubmission' => $request->resubmission ? $request->extra_days_for_resubmission : null,
                'session_year_id'             => $sessionYear->id,
                'created_by'                  => Auth::user()->id,
            );
            $assignment = $this->assignment->update($request->assignment_id, $assignmentData);

            $subject_name = $this->classSubject->findById($request->class_subject_id, ['id', 'subject_id'], ['subject'])->subject_with_name;
            $title = 'New assignment added in ' . $subject_name;
            $body = $request->name;
            $type = "assignment";

            $user = $this->student->builder()->select('user_id')->where('class_section_id', $request->class_section_id)->get()->pluck('user_id');

            send_notification($user, $title, $body, $type);

            if ($request->hasFile('file')) {
                $assignmentModelAssociate = $this->files->model()->modal()->associate($assignment);
                foreach ($request->file as $file_upload) {
                    $tempFileData = array(
                        'modal_type' => $assignmentModelAssociate->modal_type,
                        'modal_id'   => $assignmentModelAssociate->modal_id,
                        'file_name'  => $file_upload->getClientOriginalName(),
                        'type'       => 1,
                        'file_url'   => $file_upload
                    );
                    $fileData[] = $tempFileData; // Store Temp File Data in Multi-Dimensional File Data Array
                }
                $this->files->createBulk($fileData); // Store File Data
            }
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), [
                'does not exist','file_get_contents'
            ])) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function deleteAssignment(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-delete');
        try {
            DB::beginTransaction();
            $this->assignment->deleteById($request->assignment_id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getAssignmentSubmission(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-submission');
        $validator = Validator::make($request->all(), ['assignment_id' => 'required|nullable|numeric']);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $data = $this->assignmentSubmission->builder()->with('assignment.class_subject.subject:id,name,type', 'file', 'student:id,first_name,last_name,image')->where('assignment_id', $request->assignment_id)->get();

            ResponseService::successResponse('Assignment Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function updateAssignmentSubmission(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-submission');
        $validator = Validator::make($request->all(), [
            'assignment_submission_id' => 'required|numeric',
            'status'                   => 'required|numeric|in:1,2',
            'points'                   => 'nullable|numeric',
            'feedback'                 => 'nullable'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $updateAssignmentSubmissionData = array(
                'feedback' => $request->feedback,
                'points'   => $request->status == 1 ? $request->points : NULL,
                'status'   => $request->status,
            );
            $assignmentSubmission = $this->assignmentSubmission->update($request->assignment_submission_id, $updateAssignmentSubmissionData);

            $assignmentData = $this->assignment->builder()->where('id', $assignmentSubmission->assignment_id)->with('class_subject.subject')->first();
            if ($request->status == 1) {
                $title = "Assignment accepted";
                $body = $assignmentData->name . " accepted in " . $assignmentData->class_subject->subject->name_with_type . " subject";
            } else {
                $title = "Assignment rejected";
                $body = $assignmentData->name . " rejected in " . $assignmentData->class_subject->subject->name_with_type . " subject";
            }

            $type = "assignment";
            $user = $this->student->builder()->select('user_id')->where('id', $assignmentSubmission->student_id)->get()->pluck('user_id');
            send_notification($user, $title, $body, $type);
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), [
                'does not exist','file_get_contents'
            ])) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function getLesson(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('lesson-list');
        $validator = Validator::make($request->all(), [
            'lesson_id'        => 'nullable|numeric',
            'class_section_id' => 'nullable|numeric',
            'class_subject_id'       => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = $this->lesson->builder()->with('file')->withCount('topic');

            if ($request->lesson_id) {
                $sql = $sql->where('id', $request->lesson_id);
            }

            if ($request->class_section_id) {
                $sql = $sql->where('class_section_id', $request->class_section_id);
            }

            if ($request->class_subject_id) {
                $sql = $sql->where('class_subject_id', $request->class_subject_id);
            }
            $data = $sql->orderBy('id', 'DESC')->get();
            ResponseService::successResponse('Lesson Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function createLesson(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('lesson-create');
        $validator = Validator::make(
            $request->all(),
            [
                'name'                  => ['required', new uniqueLessonInClass($request->class_section_id, $request->class_subject_id)],
                'description'           => 'required',
                'class_section_id'      => 'required|numeric',
                'class_subject_id'      => 'required|numeric',
                'file'             => 'nullable|array',
                'file.*.type'      => 'required|in:file_upload,youtube_link,video_upload',
                'file.*.name'      => 'required_with:file.*.type',
                'file.*.thumbnail' => 'required_if:file.*.type,youtube_link,video_upload',
                'file.*.link'      => ['nullable', 'required_if:file.*.type,youtube_link', new YouTubeUrl], //Regex for YouTube Link
                'file.*.file'      => ['nullable', 'required_if:file.*.type,file_upload,video_upload', new DynamicMimes],
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

            if (!empty($request->file)) {
                // Initialize the Empty Array
                $lessonFileData = array();

                // Create A File Model Instance
                $lessonFile = $this->files->model();

                // Get the Association Values of File with Lesson
                $lessonModelAssociate = $lessonFile->modal()->associate($lesson);

                // Loop to the File Array from Request
                foreach ($request->file as $file) {

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
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function updateLesson(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('lesson-edit');
        $validator = Validator::make($request->all(), [
            'lesson_id'        => 'required|numeric',
            'name'             => 'required',
            'description'      => 'required',
            'class_section_id' => 'required|numeric',
            'class_subject_id'       => 'required|numeric',
            'file'             => 'nullable|array',
            'file.*.type'      => 'nullable|in:file_upload,youtube_link,video_upload',
            'file.*.name'      => 'required_with:file.*.type',
            'file.*.thumbnail' => 'required_if:file.*.type,youtube_link,video_upload',
            'file.*.file'      => 'required_if:file.*.type,file_upload,video_upload',
            'file.*.link'      => 'required_if:file.*.type,youtube_link',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $validator2 = Validator::make($request->all(), [
            'name' => [
                'required',
                new uniqueLessonInClass($request->class_section_id, $request->lesson_id)
            ]
        ]);
        if ($validator2->fails()) {
            ResponseService::errorResponse($validator2->errors()->first(), null, config('constants.RESPONSE_CODE.NOT_UNIQUE_IN_CLASS'));
        }
        try {
            DB::beginTransaction();
            $lesson = $this->lesson->update($request->lesson_id, $request->all());

            //Add the new Files
            if ($request->file) {
                // Initialize the Empty Array
                //                $lessonFileData = array();

                foreach ($request->file as $file) {
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

                        $this->files->updateOrCreate(['id' => $file['id'] ?? null], $tempFileData);
                    }
                }
            }
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function deleteLesson(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('lesson-delete');
        $validator = Validator::make($request->all(), ['lesson_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $this->lesson->deleteById($request->lesson_id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable) {
            ResponseService::errorResponse();
        }
    }

    public function getTopic(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-list');
        $validator = Validator::make($request->all(), ['lesson_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = $this->topic->builder()->with('lesson.class_section', 'lesson.class_subject.subject', 'file');
            $data = $sql->where('lesson_id', $request->lesson_id)->orderBy('id', 'DESC')->get();
            ResponseService::successResponse('Topic Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function createTopic(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-create');
        $validator = Validator::make($request->all(), [
            'lesson_id'             => 'required|numeric',
            'name'                  => ['required', new uniqueTopicInLesson($request->lesson_id)],
            'description'           => 'required',
            'file'             => 'nullable|array',
            'file.*.type'      => 'required|in:file_upload,youtube_link,video_upload,other_link',
            'file.*.name'      => 'required_with:file.*.type',
            'file.*.thumbnail' => 'required_if:file.*.type,youtube_link,video_upload,other_link',
            'file.*.link'      => ['nullable', 'required_if:file.*.type,youtube_link', new YouTubeUrl], //Regex for YouTube Link
            'file.*.file'      => ['nullable', 'required_if:file.*.type,file_upload,video_upload', new DynamicMimes],

        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $topic = $this->topic->create($request->all());

            if (!empty($request->file)) {
                // Initialize the Empty Array
                $topicFileData = array();

                // Create A File Model Instance
                $topicFile = $this->files->model();

                // Get the Association Values of File with Topic
                $topicModelAssociate = $topicFile->modal()->associate($topic);

                // Loop to the File Array from Request
                foreach ($request->file as $file) {

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
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function updateTopic(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-edit');
        $validator = Validator::make($request->all(), [
            'topic_id'         => 'required|numeric',
            'name'             => 'required',
            'description'      => 'required',
            'file'             => 'nullable|array',
            'file.*.type'      => 'nullable|in:file_upload,youtube_link,video_upload,other_link',
            'file.*.name'      => 'required_with:file.*.type',
            'file.*.thumbnail' => 'required_if:file.*.type,youtube_link,video_upload,other_link',
            'file.*.file'      => 'required_if:file.*.type,file_upload,video_upload',
            'file.*.link'      => 'required_if:file.*.type,youtube_link,other_link',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        $validator2 = Validator::make($request->all(), [
            'name' => [
                'required',
                new uniqueTopicInLesson($request->lesson_id, $request->topic_id)
            ],
        ]);
        if ($validator2->fails()) {
            ResponseService::errorResponse($validator2->errors()->first(), null, config('constants.RESPONSE_CODE.NOT_UNIQUE_IN_CLASS'));
        }
        try {
            DB::beginTransaction();
            $topic = $this->topic->update($request->topic_id, $request->all());

            //Add the new Files
            if ($request->file) {

                foreach ($request->file as $file) {
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

                        $this->files->updateOrCreate(['id' => $file['id'] ?? null], $fileData);
                    }
                }
            }

            DB::commit();

            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function deleteTopic(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Lesson Management');
        ResponseService::noPermissionThenSendJson('topic-delete');
        try {
            DB::beginTransaction();
            $this->topic->deleteById($request->topic_id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function updateFile(Request $request)
    {
        $validator = Validator::make($request->all(), ['file_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $file = File::find($request->file_id);
            $file->file_name = $request->name;


            if ($file->type == "1") {
                // Type File :- File Upload

                if (!empty($request->file)) {
                    if (Storage::disk('public')->exists($file->getRawOriginal('file_url'))) {
                        Storage::disk('public')->delete($file->getRawOriginal('file_url'));
                    }

                    if ($file->modal_type == "App\Models\Lesson") {

                        $file->file_url = $request->file->store('lessons', 'public');
                    } else if ($file->modal_type == "App\Models\LessonTopic") {

                        $file->file_url = $request->file->store('topics', 'public');
                    } else {

                        $file->file_url = $request->file->store('other', 'public');
                    }
                }
            } elseif ($file->type == "2") {
                // Type File :- YouTube Link Upload

                if (!empty($request->thumbnail)) {
                    if (Storage::disk('public')->exists($file->getRawOriginal('file_url'))) {
                        Storage::disk('public')->delete($file->getRawOriginal('file_url'));
                    }

                    if ($file->modal_type == "App\Models\Lesson") {

                        $file->file_thumbnail = $request->thumbnail->store('lessons', 'public');
                    } else if ($file->modal_type == "App\Models\LessonTopic") {

                        $file->file_thumbnail = $request->thumbnail->store('topics', 'public');
                    } else {

                        $file->file_thumbnail = $request->thumbnail->store('other', 'public');
                    }
                }
                $file->file_url = $request->link;
            } elseif ($file->type == "3") {
                // Type File :- Video Upload

                if (!empty($request->file)) {
                    if (Storage::disk('public')->exists($file->getRawOriginal('file_url'))) {
                        Storage::disk('public')->delete($file->getRawOriginal('file_url'));
                    }

                    if ($file->modal_type == "App\Models\Lesson") {

                        $file->file_url = $request->file->store('lessons', 'public');
                    } else if ($file->modal_type == "App\Models\LessonTopic") {

                        $file->file_url = $request->file->store('topics', 'public');
                    } else {

                        $file->file_url = $request->file->store('other', 'public');
                    }
                }

                if (!empty($request->thumbnail)) {
                    if (Storage::disk('public')->exists($file->getRawOriginal('file_url'))) {
                        Storage::disk('public')->delete($file->getRawOriginal('file_url'));
                    }
                    if ($file->modal_type == "App\Models\Lesson") {

                        $file->file_thumbnail = $request->thumbnail->store('lessons', 'public');
                    } else if ($file->modal_type == "App\Models\LessonTopic") {

                        $file->file_thumbnail = $request->thumbnail->store('topics', 'public');
                    } else {

                        $file->file_thumbnail = $request->thumbnail->store('other', 'public');
                    }
                }
            }
            $file->save();

            ResponseService::successResponse('Data Stored Successfully', $file);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function deleteFile(Request $request)
    {
        $validator = Validator::make($request->all(), ['file_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $this->files->deleteById($request->file_id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getAnnouncement(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Announcement Management');
        ResponseService::noPermissionThenSendJson('announcement-list');
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'nullable|numeric',
            'subject_id'       => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = $this->announcement->builder()->select('id', 'title', 'description')->with('file', 'announcement_class.class_section.class.stream', 'announcement_class.class_section.section', 'announcement_class.class_section.medium')->SubjectTeacher();
            if ($request->class_section_id) {
                $sql = $sql->whereHas('announcement_class', function ($q) use ($request) {
                    $q->where('class_section_id', $request->class_section_id);
                });
            }
            if ($request->class_subject_id) {
                $sql = $sql->whereHas('announcement_class', function ($q) use ($request) {
                    $q->where('class_subject_id', $request->class_subject_id);
                });
            }

            $data = $sql->orderBy('id', 'DESC')->paginate();
            ResponseService::successResponse('Announcement Fetched Successfully.', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function sendAnnouncement(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Announcement Management');
        ResponseService::noPermissionThenSendJson('announcement-create');
        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required|numeric',
            'class_subject_id'       => 'required|numeric',
            'title'            => 'required',
            'description'      => 'nullable',
            'file'             => 'nullable'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear(); // Get Current Session Year
            // Custom Announcement Array to Store Data
            $announcementData = array(
                'title'           => $request->title,
                'description'     => $request->description,
                'session_year_id' => $sessionYear->id,
            );

            $announcement = $this->announcement->create($announcementData); // Store Data
            $announcementClassData = array();

            if ($request->class_subject_id) {

                // When Subject is passed then Store the data according to Subject Teacher
                $teacherId = Auth::user()->id; // Teacher ID
                $subjectTeacherData = $this->subjectTeacher->builder()->where('class_section_id', $request->class_section_id)->where(['teacher_id' => $teacherId, 'class_subject_id' => $request->class_subject_id])->with('subject')->first(); // Get the Subject Teacher Data
                $subjectName = $subjectTeacherData->subject_with_name; // Subject Name

                // Check the Subject Type and Select Students According to it for Notification
                $getClassSubjectType = $this->classSubject->findById($request->class_subject_id, ['type']);
                if ($getClassSubjectType == 'Elective') {
                    $getStudentId = $this->studentSubject->builder()->select('student_id')->where('class_section_id', $request->class_section_id)->where(['class_subject_id' => $request->class_subject_id])->get()->pluck('student_id'); // Get the Student's ID According to Class Subject
                    $notifyUser = $this->student->builder()->select('user_id')->whereIn('id', $getStudentId)->get()->pluck('user_id'); // Get the Student's User ID
                } else {
                    $notifyUser = $this->student->builder()->select('user_id')->where('class_section_id', $request->class_section_id)->get()->pluck('user_id'); // Get the All Student's User ID In Specified Class
                }

                // Set class section with subject
                $announcementClassData = [
                    'announcement_id'  => $announcement->id,
                    'class_section_id' => $request->class_section_id,
                    'class_subject_id' => $request->class_subject_id
                ];
                $title = trans('New announcement in') . $subjectName; // Title for Notification
                $this->announcementClass->create($announcementClassData);
            }

            // If File Exists
            if ($request->hasFile('file')) {
                $fileData = array(); // Empty FileData Array
                $fileInstance = $this->files->model(); // Create A File Model Instance
                $announcementModelAssociate = $fileInstance->modal()->associate($announcement); // Get the Association Values of File with Announcement
                foreach ($request->file as $file_upload) {
                    // Create Temp File Data Array
                    $tempFileData = array(
                        'modal_type' => $announcementModelAssociate->modal_type,
                        'modal_id'   => $announcementModelAssociate->modal_id,
                        'file_name'  => $file_upload->getClientOriginalName(),
                        'type'       => 1,
                        'file_url'   => $file_upload
                    );
                    $fileData[] = $tempFileData; // Store Temp File Data in Multi-Dimensional File Data Array
                }
                $this->files->createBulk($fileData); // Store File Data
            }
            if ($notifyUser !== null && !empty($title)) {
                $type = 'Class Section'; // Get The Type for Notification
                $body = $request->title; // Get The Body for Notification
                send_notification($notifyUser, $title, $body, $type); // Send Notification
            }

            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), [
                'does not exist','file_get_contents'
            ])) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function updateAnnouncement(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Announcement Management');
        ResponseService::noPermissionThenSendJson('announcement-edit');
        $validator = Validator::make($request->all(), [
            'announcement_id'  => 'required|numeric',
            'class_section_id' => 'required|numeric',
            'class_subject_id'       => 'required|numeric',
            'title'            => 'required'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear(); // Get Current Session Year

            // Custom Announcement Array to Store Data
            $announcementData = array(
                'title'           => $request->title,
                'description'     => $request->description,
                'session_year_id' => $sessionYear->id,
            );

            $announcement = $this->announcement->update($request->announcement_id, $announcementData); // Store Data
            $announcementClassData = array();
            // $this->announcement->findById($request->announcement_id)->announcement_class->pluck('class_section_id')->toArray();
            $this->announcementClass->builder()->where('announcement_id', $request->announcement_id)->delete();

            //Check the Assign Data
            if ($request->class_subject_id) {

                // When Subject is passed then Store the data according to Subject Teacher
                // $teacherId = Auth::user()->teacher->id; // Teacher ID
                $teacherId = Auth::user()->id; // Teacher ID foreign key directly assign to user table

                $subjectTeacherData = $this->subjectTeacher->builder()->where('class_section_id', $request->class_section_id)->where(['teacher_id' => $teacherId, 'class_subject_id' => $request->class_subject_id])->first(); // Get the Subject Teacher Data
                $subjectName = $subjectTeacherData->subject->name; // Subject Name

                // Check the Subject Type and Select Students According to it for Notification
                $getClassSubjectType = $this->classSubject->builder()->where('id', $request->class_subject_id)->pluck('type')->first();
                if ($getClassSubjectType == 'Elective') {
                    $getStudentId = $this->studentSubject->builder()->select('student_id')->where('class_section_id', $request->class_section_id)->where(['class_subject_id' => $request->class_subject_id])->get()->pluck('student_id'); // Get the Student's ID According to Class Subject
                    $notifyUser = $this->student->builder()->select('user_id')->whereIn('id', $getStudentId)->get()->pluck('user_id'); // Get the Student's User ID
                } else {
                    $notifyUser = $this->student->builder()->select('user_id')->where('class_section_id', $request->class_section_id)->get()->pluck('user_id'); // Get the All Student's User ID In Specified Class
                }

                // Set class sections with subject
                $announcementClassData = [
                    'announcement_id'   => $announcement->id,
                    'class_section_id'  => $request->class_section_id,
                    'class_subject_id'  => $request->class_subject_id
                ];

                $title = trans('Updated announcement in') . $subjectName; // Title for Notification


            }

            $this->announcementClass->create($announcementClassData);

            // Delete announcement class sections
            // $this->announcementClass->builder()->where('announcement_id', $request->announcement_id)->where('class_section_id', $oldClassSection)->delete();


            // If File Exists
            if ($request->hasFile('file')) {
                $fileData = array(); // Empty FileData Array
                $fileInstance = $this->files->model(); // Create A File Model Instance
                $announcementModelAssociate = $fileInstance->modal()->associate($announcement); // Get the Association Values of File with Announcement
                foreach ($request->file as $file_upload) {
                    // Create Temp File Data Array
                    $tempFileData = array(
                        'modal_type' => $announcementModelAssociate->modal_type,
                        'modal_id'   => $announcementModelAssociate->modal_id,
                        'file_name'  => $file_upload->getClientOriginalName(),
                        'type'       => 1,
                        'file_url'   => $file_upload
                    );
                    $fileData[] = $tempFileData; // Store Temp File Data in Multi-Dimensional File Data Array
                }
                $this->files->createBulk($fileData); // Store File Data
            }

            if ($notifyUser !== null && !empty($title)) {
                $type = $request->aissgn_to; // Get The Type for Notification
                $body = $request->title; // Get The Body for Notification
                // send_notification($notifyUser, $title, $body, $type); // Send Notification
            }

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), [
                'does not exist','file_get_contents'
            ])) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function deleteAnnouncement(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Announcement Management');
        ResponseService::noPermissionThenSendJson('announcement-delete');
        $validator = Validator::make($request->all(), ['announcement_id' => 'required|numeric',]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $this->announcement->deleteById($request->announcement_id);
            DB::commit();
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getAttendance(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Attendance Management');
        ResponseService::noAnyPermissionThenSendJson(['attendance-list','class-teacher']);
        $class_section_id = $request->class_section_id;
        $attendance_type = $request->type;
        $date = date('Y-m-d', strtotime($request->date));

        $validator = Validator::make($request->all(), [
            'class_section_id' => 'required',
            'date'             => 'required|date',
            'type'             => 'in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError();
        }
        try {
            $sql = $this->attendance->builder()->with('user:id,first_name,last_name,image', 'user.student:id,user_id,roll_number')->where('class_section_id', $class_section_id)->where('date', $date);
            if (isset($attendance_type) && $attendance_type != '') {
                $sql->where('type', $attendance_type);
            }
            $data = $sql->get();
            $holiday = $this->holiday->builder()->where('date', $date)->get();
            if ($holiday->count()) {
                ResponseService::successResponse("Data Fetched Successfully", $data, [
                    'is_holiday' => true,
                    'holiday'    => $holiday,
                ]);
            } else if ($data->count()) {
                if ($data->first()->type == 3) {
                    ResponseService::successResponse("Data Fetched Successfully", $data, ['is_holiday' => true]);
                } else {
                    ResponseService::successResponse("Data Fetched Successfully", $data, ['is_holiday' => false]);
                }
            } else {
                ResponseService::successResponse("Attendance not recorded", $data, ['is_holiday' => false]);
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function submitAttendance(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Attendance Management');
        ResponseService::noAnyPermissionThenSendJson([
            'attendance-create',
            'attendance-edit',
            'class-teacher'
        ]);
        $validator = Validator::make($request->all(), [
            'class_section_id'        => 'required',
            // 'attendance.*.student_id' => 'required',
            // 'attendance.*.type'       => 'required|in:0,1,3',
            'date'                    => 'required|date',
            'holiday'                 => 'in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError();
        }
        try {
            DB::beginTransaction();
            $sessionYear = $this->cache->getDefaultSessionYear();
            $date = date('Y-m-d', strtotime($request->date));
            $student_ids = array();

            if ($request->holiday) {
                $users = $this->student->builder()->where('class_section_id',$request->class_section_id)->get();
                foreach ($users as $key => $user) {
                    $attendanceData = [
                        'class_section_id' => $request->class_section_id,
                        'student_id'       => $user->user_id,
                        'session_year_id'  => $sessionYear->id,
                        'type'             => 3,
                        'date'             => date('Y-m-d', strtotime($request->date)),
                    ];

                    $attendance = $this->attendance->builder()->where('class_section_id', $request->class_section_id)->where('student_id', $user->user_id)->whereDate('date', $date)->first();
                    if ($attendance) {
                        $this->attendance->update($attendance->id, $attendanceData);
                    } else {
                        $this->attendance->create($attendanceData);
                    }
                }
            } else {
                for ($i = 0, $iMax = count($request->attendance); $i < $iMax; $i++) {

                    $attendanceData = [
                        'class_section_id' => $request->class_section_id,
                        'student_id'       => $request->attendance[$i]['student_id'],
                        'session_year_id'  => $sessionYear->id,
                        'type'             => $request->attendance[$i]['type'],
                        'date'             => date('Y-m-d', strtotime($request->date)),
                    ];
    
                    if ($request->attendance[$i]['type'] == 0) {
                        $student_ids[] = $request->attendance[$i]['student_id'];
                    }
    
    
                    $attendance = $this->attendance->builder()->where('class_section_id', $request->class_section_id)->where('student_id', $request->attendance[$i]['student_id'])->whereDate('date', $date)->first();
                    if ($attendance) {
                        $this->attendance->update($attendance->id, $attendanceData);
                    } else {
                        $this->attendance->create($attendanceData);
                    }
                }
    
                if ($request->absent_notification) {
                    $user = $this->student->builder()->whereIn('user_id', $student_ids)->pluck('guardian_id')->toArray();
                    $date = Carbon::parse(date('Y-m-d', strtotime($request->date)))->format('F jS, Y');
                    $title = 'Absent';
                    $body = 'Your child is absent on ' . $date;
                    $type = "attendance";
    
                    send_notification($user, $title, $body, $type);
                }
            }
            
            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            if (Str::contains($e->getMessage(), [
                'does not exist','file_get_contents'
            ])) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function getStudentList(Request $request)
    {
        $validator = Validator::make($request->all(), ['class_section_id' => 'required|numeric',]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            if ($request->student_id) {
                $sql = $this->user->builder()->whereHas('student', function ($q) use ($request) {
                    $q->where('class_section_id', $request->class_section_id);
                })->with('student.guardian','student.class_section.class','student.class_section.section','student.class_section.medium')->withTrashed()->first();
            } else {

                // $subjectTeacherIds = SubjectTeacher::where('teacher_id', Auth::user()->id)
                // ->where('class_section_id', $request->class_section_id)
                // ->pluck('class_subject_id')->toArray();

                $sql = $this->user->builder()->whereHas('student', function ($q) use ($request) {
                    $q->where('class_section_id', $request->class_section_id);
                })
                ->with('student.guardian','student.class_section','student.class_section.class','student.class_section.section','student.class_section.medium')->has('student')->role('Student');
                // ->whereHas('student_subject',function($q) use($subjectTeacherIds) {
                //     $q->whereIn('class_subject_id',$subjectTeacherIds);
                // });

                if ($request->status != 1) {
                    if ($request->status == 2) {
                        $sql->onlyTrashed();
                    } else if ($request->status == 0) {
                        $sql->withTrashed();
                    } else {
                        $sql->withTrashed();
                    }
                }


                if ($request->search) {
                    $sql->where(function ($q) use ($request) {
                        $q->when($request->search, function ($q) use ($request) {
                            $q->where('first_name', 'LIKE', "%$request->search%")
                                ->orwhere('last_name', 'LIKE', "%$request->search%")
                                ->orwhere('mobile', 'LIKE', "%$request->search%")
                                ->orwhere('email', 'LIKE', "%$request->search%")
                                ->orwhere('gender', 'LIKE', "%$request->search%")
                                ->orWhereRaw('concat(first_name," ",last_name) like ?', "%$request->search%")
                                ->where(function ($q) use ($request) {
                                    $q->when($request->session_year_id, function ($q) use ($request) {
                                        $q->whereHas('student', function ($q) use ($request) {
                                            $q->where('session_year_id', $request->session_year_id);
                                        });
                                    });
                                });
                        });
                    });
                }

                if ($request->session_year_id) {
                    $sql = $sql->whereHas('student', function ($q) use ($request) {
                        $q->where('session_year_id', $request->session_year_id);
                    });
                }

                if (($request->paginate || $request->paginate != 0 || $request->paginate == null)) {
                    $sql = $sql->has('student')->orderBy('id')->paginate(10);
                } else {
                    $sql = $sql->has('student')->orderBy('id')->get();
                }

                // 
                if ($request->exam_id) {

                    $validator = Validator::make($request->all(), ['class_subject_id' => 'required']);
                    if ($validator->fails()) {
                        ResponseService::validationError($validator->errors()->first());
                    }

                    $exam = $this->exam->builder()->with('timetable:id,date,exam_id,start_time,end_time')->where('id', $request->exam_id)->first();

                    // Get Student ids according to Subject is elective or compulsory
                    $classSubject = $this->classSubject->findById($request->class_subject_id);
                    if ($classSubject->type == "Elective") {
                        $studentIds = $this->studentSubject->builder()->where(['class_section_id' => $request->class_section_id, 'class_subject_id' => $classSubject->id])->pluck('student_id');
                    } else {
                        $studentIds = $this->user->builder()->role('student')->whereHas('student', function ($query) use ($request) {
                            $query->where('class_section_id', $request->class_section_id);
                        })->pluck('id');
                    }

                    // Get Timetable Data
                    $timetable = $exam->timetable()->where('class_subject_id', $request->class_subject_id)->first();

                    // return $timetable;

                    // IF Timetable is empty then show error message
                    if (!$timetable) {
                        return response()->json(['error' => true, 'message' => trans('Exam Timetable Does not Exists')]);
                    }

                    // IF Exam status is not 3 that is exam not completed then show error message
                    if ($exam->exam_status != 3) {
                        ResponseService::errorResponse('Exam not completed yet');
                    }

                    $sessionYear = $this->cache->getDefaultSessionYear(); // Get Students Data on the basis of Student ids

                    $sql = $this->user->builder()->select('id', 'first_name', 'last_name', 'image')->role('Student')->whereIn('id', $studentIds)->with(['marks' => function ($query) use ($timetable) {
                        $query->where('exam_timetable_id', $timetable->id)->select('id', 'exam_timetable_id', 'student_id', 'obtained_marks');
                    }])
                        ->whereHas('student', function ($q) use ($sessionYear) {
                            $q->where('session_year_id', $sessionYear->id);
                        })->get();
                }
            }

            ResponseService::successResponse("Student Details Fetched Successfully", $sql);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getStudentDetails(Request $request)
    {
        $validator = Validator::make($request->all(), ['student_id' => 'required|numeric',]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $student_data = $this->student->findById($request->student_id, ['user_id', 'class_section_id', 'guardian_id'], ['user', 'guardian']);

            $student_total_present = $this->attendance->builder()->where('student_id', $student_data->user_id)->where('type', 1)->count();
            $student_total_absent = $this->attendance->builder()->where('student_id', $student_data->user_id)->where('type', 0)->count();

            $today_date_string = Carbon::now();
            $today_date_string->toDateTimeString();
            $today_date = date('Y-m-d', strtotime($today_date_string));

            $student_today_attendance = $this->attendance->builder()->where('student_id', $student_data->user_id)->where('date', $today_date)->first();

            if ($student_today_attendance) {
                if ($student_today_attendance->type == 1) {
                    $today_attendance = 'Present';
                } else {
                    $today_attendance = 'Absent';
                }
            } else {
                $today_attendance = 'Not Taken';
            }
            ResponseService::successResponse("Student Details Fetched Successfully", null, [
                'data'             => $student_data,
                'total_present'    => $student_total_present,
                'total_absent'     => $student_total_absent,
                'today_attendance' => $today_attendance ?? ''
            ]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getTeacherTimetable(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');
        try {
            $timetable = $this->timetable->builder()->whereHas('subject_teacher', function ($q) {
                $q->where('teacher_id', Auth::user()->id);
            })->with('class_section.class.stream', 'class_section.section', 'subject')->orderBy('start_time', 'ASC')->get();


            ResponseService::successResponse("Timetable Fetched Successfully", $timetable);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function submitExamMarksBySubjects(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        $validator = Validator::make($request->all(), [
            'exam_id'    => 'required|numeric',
            'class_subject_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $exam_published = $this->exam->builder()->where('id', $request->exam_id)->first();
            if (isset($exam_published) && $exam_published->publish == 1) {
                ResponseService::errorResponse('exam_published', null, config('constants.RESPONSE_CODE.EXAM_ALREADY_PUBLISHED'));
            }

            $currentTime = Carbon::now();
            $current_date = date($currentTime->toDateString());
            if ($current_date >= $exam_published->start_date && $current_date <= $exam_published->end_date) {
                $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
            } elseif ($current_date < $exam_published->start_date) {
                $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
            } else {
                $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
            }
            if ($exam_status != 2) {
                ResponseService::errorResponse('exam_not_completed_yet', null, config('constants.RESPONSE_CODE.EXAM_ALREADY_PUBLISHED'));
            } else {

                $exam_timetable = $this->examTimetable->builder()->where('exam_id', $request->exam_id)->where('class_subject_id', $request->class_subject_id)->firstOrFail();

                foreach ($request->marks_data as $marks) {
                    if ($marks['obtained_marks'] > $exam_timetable['total_marks']) {
                        ResponseService::errorResponse('The obtained marks that did not exceed the total marks');
                    }
                    $passing_marks = $exam_timetable->passing_marks;
                    if ($marks['obtained_marks'] >= $passing_marks) {
                        $status = 1;
                    } else {
                        $status = 0;
                    }
                    $marks_percentage = ($marks['obtained_marks'] / $exam_timetable['total_marks']) * 100;

                    $exam_grade = findExamGrade($marks_percentage);
                    if ($exam_grade == null) {
                        ResponseService::errorResponse('grades_data_does_not_exists', null, config('constants.RESPONSE_CODE.GRADES_NOT_FOUND'));
                    }

                    $exam_marks = $this->examMarks->builder()->where('exam_timetable_id', $exam_timetable->id)->where('class_subject_id', $request->class_subject_id)->where('student_id', $marks['student_id'])->first();
                    if ($exam_marks) {
                        $exam_data = [
                            'obtained_marks' => $marks['obtained_marks'],
                            'passing_status' => $status,
                            'grade' => $exam_grade
                        ];
                        $this->examMarks->update($exam_marks->id, $exam_data);
                    } else {
                        $exam_result_marks[] = array(
                            'exam_timetable_id' => $exam_timetable->id,
                            'student_id'        => $marks['student_id'],
                            'class_subject_id'        => $request->class_subject_id,
                            'obtained_marks'    => $marks['obtained_marks'],
                            'passing_status'    => $status,
                            'session_year_id'   => $exam_timetable->session_year_id,
                            'grade'             => $exam_grade,
                        );
                    }
                }
                if (isset($exam_result_marks)) {
                    $this->examMarks->createBulk($exam_result_marks);
                }
                DB::commit();
                ResponseService::successResponse('Data Stored Successfully');
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function submitExamMarksByStudent(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        $validator = Validator::make($request->all(), [
            'exam_id'    => 'required|numeric',
            'student_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $exam_published = $this->exam->findById($request->exam_id);
            if (isset($exam_published) && $exam_published->publish == 1) {

                ResponseService::errorResponse('exam_published', null, config('constants.RESPONSE_CODE.EXAM_ALREADY_PUBLISHED'));
            }

            $currentTime = Carbon::now();
            $current_date = date($currentTime->toDateString());
            if ($current_date >= $exam_published->start_date && $current_date <= $exam_published->end_date) {
                $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
            } elseif ($current_date < $exam_published->start_date) {
                $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
            } else {
                $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
            }

            if ($exam_status != 2) {
                ResponseService::errorResponse('exam_published', null, config('constants.RESPONSE_CODE.EXAM_NOT_COMPLETED'));
            } else {

                foreach ($request->marks_data as $marks) {
                    $exam_timetable = $this->examTimetable->builder()->where('exam_id', $request->exam_id)->where('class_subject_id', $marks['class_subject_id'])->firstOrFail();

                    if ($marks['obtained_marks'] > $exam_timetable['total_marks']) {
                        ResponseService::errorResponse('The obtained marks that did not exceed the total marks');
                    }
                    $passing_marks = $exam_timetable->passing_marks;
                    if ($marks['obtained_marks'] >= $passing_marks) {
                        $status = 1;
                    } else {
                        $status = 0;
                    }
                    $marks_percentage = ($marks['obtained_marks'] / $exam_timetable->total_marks) * 100;

                    $exam_grade = findExamGrade($marks_percentage);
                    if ($exam_grade == null) {
                        ResponseService::errorResponse('grades_data_does_not_exists', null, config('constants.RESPONSE_CODE.GRADES_NOT_FOUND'));
                    }
                    $exam_marks = $this->examMarks->builder()->where('exam_timetable_id', $exam_timetable->id)->where('class_subject_id', $marks['class_subject_id'])->where('student_id', $request->student_id)->first();
                    if ($exam_marks) {
                        $exam_data = [
                            'obtained_marks' => $marks['obtained_marks'],
                            'passing_status' => $status,
                            'grade' => $exam_grade
                        ];
                        $this->examMarks->update($exam_marks->id, $exam_data);
                    } else {
                        $exam_result_marks[] = array(
                            'exam_timetable_id' => $exam_timetable->id,
                            'student_id'        => $request->student_id,
                            'class_subject_id'        => $marks['class_subject_id'],
                            'obtained_marks'    => $marks['obtained_marks'],
                            'passing_status'    => $status,
                            'session_year_id'   => $exam_timetable->session_year_id,
                            'grade'             => $exam_grade,
                        );
                    }
                }
                if (isset($exam_result_marks)) {
                    $this->examMarks->createBulk($exam_result_marks);
                }

                DB::commit();
                ResponseService::successResponse('Data Stored Successfully');
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function GetStudentExamResult(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        $validator = Validator::make($request->all(), ['student_id' => 'required|nullable']);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $exam_marks_db = $this->exam->builder()->with(['timetable.exam_marks' => function ($q) use ($request) {
                $q->where('student_id', $request->student_id);
            }])->has('timetable.exam_marks')->with('timetable.class_subject.subject')->has('results')->with(['results' => function ($q) use ($request) {
                $q->where('student_id', $request->student_id)->with('class_section.class.stream', 'class_section.section', 'class_section.medium');
            }])->get();

            if (count($exam_marks_db)) {
                foreach ($exam_marks_db as $data_db) {
                    $currentTime = Carbon::now();
                    $current_date = date($currentTime->toDateString());
                    if ($current_date >= $data_db->start_date && $current_date <= $data_db->end_date) {
                        $exam_status = "1"; // Upcoming = 0 , On Going = 1 , Completed = 2
                    } elseif ($current_date < $data_db->start_date) {
                        $exam_status = "0"; // Upcoming = 0 , On Going = 1 , Completed = 2
                    } else {
                        $exam_status = "2"; // Upcoming = 0 , On Going = 1 , Completed = 2
                    }

                    // check whether exam is completed or not
                    if ($exam_status == 2) {
                        $marks_array = array();

                        // check whether timetable exists or not
                        if (count($data_db->timetable)) {
                            foreach ($data_db->timetable as $timetable_db) {
                                $total_marks = $timetable_db->total_marks;
                                $exam_marks = array();
                                if (count($timetable_db->exam_marks)) {
                                    foreach ($timetable_db->exam_marks as $marks_data) {
                                        $exam_marks = array(
                                            'marks_id'       => $marks_data->id,
                                            'subject_name'   => $marks_data->class_subject->subject->name,
                                            'subject_type'   => $marks_data->class_subject->subject->type,
                                            'total_marks'    => $total_marks,
                                            'obtained_marks' => $marks_data->obtained_marks,
                                            'grade'          => $marks_data->grade,
                                        );
                                    }
                                } else {
                                    $exam_marks = (object)[];
                                }

                                $marks_array[] = array(
                                    'subject_id'   => $timetable_db->class_subject->subject_id,
                                    'subject_name' => $timetable_db->class_subject->subject->name,
                                    'subject_type' => $timetable_db->class_subject->subject->type,
                                    'total_marks'  => $total_marks,
                                    'subject_code' => $timetable_db->class_subject->subject->code,
                                    'marks'        => $exam_marks
                                );
                            }
                            $exam_result = array();
                            if (count($data_db->results)) {
                                foreach ($data_db->results as $result_data) {
                                    $exam_result = array(
                                        'result_id'      => $result_data->id,
                                        'exam_id'        => $result_data->exam_id,
                                        'exam_name'      => $data_db->name,
                                        'class_name'     => $result_data->class_section->full_name,
                                        'student_name'   => $result_data->user->first_name . ' ' . $result_data->user->last_name,
                                        'exam_date'      => $data_db->start_date,
                                        'total_marks'    => $result_data->total_marks,
                                        'obtained_marks' => $result_data->obtained_marks,
                                        'percentage'     => $result_data->percentage,
                                        'grade'          => $result_data->grade,
                                        'session_year'   => $result_data->session_year->name,
                                    );
                                }
                            } else {
                                $exam_result = (object)[];
                            }
                            $data[] = array(
                                'exam_id'    => $data_db->id,
                                'exam_name'  => $data_db->name,
                                'exam_date'  => $data_db->start_date,
                                'marks_data' => $marks_array,
                                'result'     => $exam_result
                            );
                        }
                    }
                }
                ResponseService::successResponse("Exam Marks Fetched Successfully", $data ?? []);
            } else {
                ResponseService::successResponse("Exam Marks Fetched Successfully", []);
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function GetStudentExamMarks(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        $validator = Validator::make($request->all(), ['student_id' => 'required|nullable']);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sessionYear = $this->cache->getDefaultSessionYear();
            $exam_marks_db = $this->exam->builder()->with(['timetable.exam_marks' => function ($q) use ($request) {
                $q->where('student_id', $request->student_id);
            }])->has('timetable.exam_marks')->with('timetable.class_subject')->where('session_year_id', $sessionYear->id)->get();


            if (count($exam_marks_db)) {
                foreach ($exam_marks_db as $data_db) {
                    $marks_array = array();
                    foreach ($data_db->timetable as $marks_db) {
                        $exam_marks = array();
                        if (count($marks_db->exam_marks)) {
                            foreach ($marks_db->exam_marks as $marks_data) {
                                $exam_marks = array(
                                    'marks_id'       => $marks_data->id,
                                    'subject_name'   => $marks_data->class_subject->subject->name,
                                    'subject_type'   => $marks_data->class_subject->subject->type,
                                    'total_marks'    => $marks_data->timetable->total_marks,
                                    'obtained_marks' => $marks_data->obtained_marks,
                                    'grade'          => $marks_data->grade,
                                );
                            }
                        } else {
                            $exam_marks = [];
                        }

                        $marks_array[] = array(
                            'subject_id'   => $marks_db->class_subject->subject_id,
                            'subject_name' => $marks_db->subject_with_name,
                            'marks'        => $exam_marks
                        );
                    }
                    $data[] = array(
                        'exam_id'    => $data_db->id,
                        'exam_name'  => $marks_db->exam->name ?? '',
                        'marks_data' => $marks_array
                    );
                }
                ResponseService::successResponse("Exam Marks Fetched Successfully", $data ?? '');
            } else {
                ResponseService::successResponse("Exam Marks Fetched Successfully", []);
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getExamList(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        $validator = Validator::make($request->all(), [
            'status'  => 'in:0,1,2,3',
            'publish' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sessionYear = $this->cache->getDefaultSessionYear();

            $sql = $this->exam->builder()->with('session_year', 'class')->select('id', 'name', 'description', 'class_id', 'start_date', 'end_date', 'session_year_id', 'publish')
                ->with(['timetable.class_subject' => function ($q) {
                    $q->withTrashed();
                }]);


            if (isset($request->publish)) {
                $sql = $sql->where('publish', $request->publish);
            }
            if ($request->session_year_id) {
                $sql = $sql->where('session_year_id', $request->session_year_id);
            } else {
                $sql = $sql->where('session_year_id', $sessionYear->id);
            }

            if ($request->class_section_id) {
                $sql = $sql->whereHas('class.class_sections', function ($q) use ($request) {
                    $q->where('id', $request->class_section_id);
                });
            }

            if ($request->medium_id) {
                $sql = $sql->whereHas('class', function ($q) use ($request) {
                    $q->where('medium_id', $request->medium_id);
                });
            }

            $exam_data_db = $sql->orderBy('id', 'DESC')->get();
            foreach ($exam_data_db as $data) {

                $currentTime = Carbon::now();
                $current_date = date($currentTime->toDateString());
                $current_time = Carbon::now();
                //  0- Upcoming, 1-On Going, 2-Completed, 3-All Details
                $exam_status = "3";
                if ($current_date == $data->start_date && $current_date == $data->end_date) {
                    if (count($data->timetable)) {
                        $exam_end_time = Carbon::parse($data->timetable->first()->end_time);
                        $exam_start_time = Carbon::parse($data->timetable->first()->start_time);
                        // if ($exam_start_time->gt($current_time)) {
                        //     $exam_status = "1";
                        // } else if ($exam_end_time->lt($current_time)){
                        //     $exam_status = "3";
                        // } else {
                        //     $exam_status = "2";
                        // }

                        if ($current_time->lt($exam_start_time)) {
                            $exam_status = "0";
                        } elseif ($current_time->gt($exam_end_time)) {
                            $exam_status = "2";
                        } else {
                            $exam_status = "1";
                        }
                    }
                } else {
                    if ($current_date >= $data->start_date && $current_date <= $data->end_date) {
                        $exam_status = "1";
                    } else if ($current_date < $data->start_date) {
                        $exam_status = "0";
                    } else if ($current_date >= $data->end_date) {
                        $exam_status = "2";
                    } else {
                        $exam_status = null;
                    }
                }

                $timetable_data = array();
                if (count($data->timetable)) {
                    foreach ($data->timetable as $key => $timetable) {
                        $subject = [
                            'id' => $timetable->class_subject->subject->id,
                            'name' => $timetable->class_subject->subject->name,
                            'type' => $timetable->class_subject->subject->type,
                        ];
                        $class_subject = [
                            'id' => $timetable->class_subject->id,
                            'class_id' => $timetable->class_subject->id,
                            'subject_id' => $timetable->class_subject->id,
                            'subject' => $subject
                        ];
                        $timetable_data[] = [
                            'id' => $timetable->id,
                            'total_marks' => $timetable->total_marks,
                            'passing_marks' => $timetable->passing_marks,
                            'date' => $timetable->date,
                            'start_time' => $timetable->start_time,
                            'end_time' => $timetable->end_time,
                            'subject_name' => $timetable->subject_with_name,
                            'class_subject' => $class_subject
                        ];
                    }
                }

                if (isset($request->status) && $request->status != 3) {
                    if ($request->status == 0 && $exam_status == 0) {
                        $exam_data[] = array(
                            'id'                 => $data->id,
                            'name'               => $data->name,
                            'description'        => $data->description,
                            'publish'            => $data->publish,
                            'session_year'       => $data->session_year->name,
                            'exam_starting_date' => $data->start_date,
                            'exam_ending_date'   => $data->end_date,
                            'exam_status'        => $exam_status,
                            'class_name'        => $data->class_name,
                            'timetable'         => $timetable_data,
                        );
                    } else if ($request->status == 1) {
                        if ($exam_status == 1) {
                            $exam_data[] = array(
                                'id'                 => $data->id,
                                'name'               => $data->name,
                                'description'        => $data->description,
                                'publish'            => $data->publish,
                                'session_year'       => $data->session_year->name,
                                'exam_starting_date' => $data->start_date,
                                'exam_ending_date'   => $data->end_date,
                                'exam_status'        => $exam_status,
                                'class_name'        => $data->class_name,
                                'timetable'         => $timetable_data,
                            );
                        }
                    } else if ($exam_status == 2 && $request->status == 2) {
                        $exam_data[] = array(
                            'id'                 => $data->id,
                            'name'               => $data->name,
                            'description'        => $data->description,
                            'publish'            => $data->publish,
                            'session_year'       => $data->session_year->name,
                            'exam_starting_date' => $data->start_date,
                            'exam_ending_date'   => $data->end_date,
                            'exam_status'        => $exam_status,
                            'class_name'        => $data->class_name,
                            'timetable'         => $timetable_data,
                        );
                    } else if ($request->status == 3 && count($data->timetable) && $data->exam_status == 3) {
                        $exam_data[] = array(
                            'id'                 => $data->id,
                            'name'               => $data->name,
                            'description'        => $data->description,
                            'publish'            => $data->publish,
                            'session_year'       => $data->session_year->name,
                            'exam_starting_date' => $data->start_date,
                            'exam_ending_date'   => $data->end_date,
                            'exam_status'        => $exam_status,
                            'class_name'        => $data->class_name,
                            'timetable'         => $timetable_data,
                        );
                    }
                } else {
                    $exam_data[] = array(
                        'id'                 => $data->id,
                        'name'               => $data->name,
                        'description'        => $data->description,
                        'publish'            => $data->publish,
                        'session_year'       => $data->session_year->name,
                        'exam_starting_date' => $data->start_date,
                        'exam_ending_date'   => $data->end_date,
                        'exam_status'        => $exam_status,
                        'class_name'        => $data->class_name,
                        'timetable'         => $timetable_data,
                    );
                }


                // $exam_data['timetable'] = $timetable_data;
            }

            ResponseService::successResponse('Data Fetched Successfully', $exam_data ?? []);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getExamDetails(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Exam Management');
        $validator = Validator::make($request->all(), ['exam_id' => 'required|nullable',]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $exam_data = $this->exam->builder()->select('id', 'name', 'description', 'session_year_id', 'publish')->with('timetable.class_subject.subject')->where('id', $request->exam_id)->first();

            ResponseService::successResponse('Data Fetched Successfully', $exam_data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getClassDetail(Request $request)
    {
        try {

            $sql = $this->classSection->builder()->with('class.stream', 'medium', 'section', 'class_teachers.teacher:id,first_name,last_name', 'subject_teachers.subject:id,name,code,type', 'subject_teachers.teacher:id,first_name,last_name');
            if ($request->class_id) {
                $sql = $sql->where('class_id', $request->class_id);
            }

            // $sql = $this->classSection->builder()->with(['class.stream', 'medium', 'section', 'class_teachers.teacher:id,first_name,last_name',  'subject_teachers'=> function ($q) {
            //     $q->with('teacher:id,first_name,last_name')
            //     ->has('class_subject')->with(['class_subject' => function($q) {
            //         $q->whereNull('deleted_at')->with('semester');
            //     }])
            //     ->with('subject')->owner();
            // }]);

            // if ($request->class_id) {
            //     $sql = $sql->where('class_id', $request->class_id);
            // }


            $sql = $sql->orderBy('id', 'DESC')->get();
            ResponseService::successResponse('Data Fetched Successfully', $sql);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
}

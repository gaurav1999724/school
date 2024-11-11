<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class LessonTopic extends Model {
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'lesson_id',
        'school_id'
    ];


    protected static function boot() {
        parent::boot();
        static::deleting(static function ($topic) { // before delete() method call this
            if ($topic->file) {
                foreach ($topic->file as $file) {
                    if (Storage::disk('public')->exists($file->getRawOriginal('file_url'))) {
                        Storage::disk('public')->delete($file->getRawOriginal('file_url'));
                    }
                    if ($file->file_thumbnail && Storage::disk('public')->exists($file->getRawOriginal('file_thumbnail'))) {
                        Storage::disk('public')->delete($file->getRawOriginal('file_thumbnail'));
                    }
                }
                $topic->file()->delete();
            }
        });
    }

    public function scopeOwner($query) {
        if (Auth::user()->hasRole('Super Admin')) {
            return $query;
        }

        if (Auth::user()->hasRole('School Admin')) {
            return $query->where('school_id', Auth::user()->school_id);
        }

        if (Auth::user()->hasRole('Teacher')) {
            // $subject_teacher = SubjectTeacher::select(['class_section_id', 'class_subject_id'])->where(['teacher_id' => Auth::user()->id, 'school_id' => Auth::user()->school_id])->get();
            // if ($subject_teacher) {
            //     $subject_teacher = $subject_teacher->toArray();
            //     $class_section_id = array_column($subject_teacher, 'class_section_id');
            //     $class_subject_id = array_column($subject_teacher, 'class_subject_id');
            //     $lesson_id = Lesson::select('id')->whereIn('class_section_id', $class_section_id)->whereIn('class_subject_id',$class_subject_id)->get()->pluck('id');
            //     return $query->whereIn('lesson_id', $lesson_id);
            // }

            $teacherId = Auth::user()->id;
            return $query->whereHas('lesson.subject_teacher', function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId)
                      ->whereColumn('class_section_id', 'lessons.class_section_id');
            })->where('school_id',Auth::user()->school_id);
            return $query->where('school_id', Auth::user()->school_id);

            return $query;
        }

        /* Sagar's Code For Backup
         * if (Auth::user()->hasRole('Teacher')) {
            $class_subject_id = SubjectTeacher::select(['class_section_id', 'subject_id'])->where(['teacher_id' => Auth::user()->id, 'school_id' => Auth::user()->school_id])->get()->pluck('class_subject_id');
            if ($class_subject_id) {
//                $class_section_id = array_column($subject_teacher, 'class_section_id');
//                $subject_id = array_column($subject_teacher, 'subject_id');
                $lesson_id = Lesson::select('id')->whereIn('class_subject_id', $class_subject_id->toArray())->pluck('id');
                return $query->whereIn('lesson_id', $lesson_id);
            }
            return $query;
        }*/

        if (Auth::user()->hasRole('Student')) {
            return $query->where('school_id', Auth::user()->school_id);
        }

        return $query;
    }


    public function file() {
        return $this->morphMany(File::class, 'modal');
    }

    public function lesson() {
        return $this->belongsTo(Lesson::class);
    }

    // public function scopeLessonTopicTeachers($query)
    // {
    //     $user = Auth::user();
    //     if ($user->hasRole('Teacher')) {
    //         // $teacher_id = $user->teacher()->select('id')->pluck('id')->first();
    //         // $subject_teacher = SubjectTeacher::select('class_section_id', 'subject_id')->where('teacher_id', $teacher_id)->get();

    //         $subject_teacher = SubjectTeacher::select('class_section_id', 'subject_id')->where('teacher_id', $user->id)->get();
    //         if ($subject_teacher) {
    //             $subject_teacher = $subject_teacher->toArray();
    //             $class_section_id = array_column($subject_teacher, 'class_section_id');
    //             $subject_id = array_column($subject_teacher, 'subject_id');
    //             $lesson_id = Lesson::select('id')->whereIn('class_section_id', $class_section_id)->whereIn('subject_id', $subject_id)->get()->pluck('id');
    //             return $query->whereIn('lesson_id', $lesson_id);
    //         }
    //         return $query;
    //     }
    //     return $query;
    // }


}

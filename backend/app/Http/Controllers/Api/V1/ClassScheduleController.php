<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassScheduleController extends Controller
{
    private function school(): int { return CurrentSchool::id(); }

    private function scheduleQuery()
    {
        return DB::table('class_schedule_entries as se')
            ->join('classrooms as c', 'c.id', '=', 'se.classroom_id')
            ->join('grade_subjects as gs', 'gs.id', '=', 'se.grade_subject_id')
            ->join('subjects as s', 's.id', '=', 'gs.subject_id')
            ->join('teacher_profiles as tp', 'tp.id', '=', 'se.teacher_profile_id')
            ->join('users as u', 'u.id', '=', 'tp.user_id')
            ->join('academic_years as ay', 'ay.id', '=', 'c.academic_year_id')
            ->join('terms as t', 't.id', '=', 'gs.term_id')
            ->where('ay.is_current', true)->where('t.is_active', true)
            ->where('se.school_id', $this->school())
            ->select('se.id','se.classroom_id','se.grade_subject_id','se.teacher_profile_id','se.weekday',
                'se.starts_at','se.ends_at','se.room','c.name as classroom','s.name as subject','u.name as teacher');
    }

    public function adminIndex(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['school_admin','super_admin'], true), 403);
        return response()->json(['entries' => $this->scheduleQuery()->orderBy('se.weekday')->orderBy('se.starts_at')->get()]);
    }

    private function validateEntry(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'classroom_id' => ['required','integer'], 'grade_subject_id' => ['required','integer'],
            'teacher_profile_id' => ['required','integer'], 'weekday' => ['required','integer','between:0,6'],
            'starts_at' => ['required','date_format:H:i'], 'ends_at' => ['required','date_format:H:i','after:starts_at'],
            'room' => ['nullable','string','max:80'],
        ]);
        $valid = DB::table('teacher_assignments as ta')
            ->join('classrooms as c','c.id','=','ta.classroom_id')
            ->join('grade_subjects as gs','gs.id','=','ta.grade_subject_id')
            ->join('terms as t','t.id','=','gs.term_id')
            ->where('ta.school_id',$this->school())->where('ta.teacher_profile_id',$data['teacher_profile_id'])
            ->where('ta.grade_subject_id',$data['grade_subject_id'])->where('ta.classroom_id',$data['classroom_id'])
            ->whereColumn('c.grade_id','gs.grade_id')->whereColumn('c.academic_year_id','ta.academic_year_id')
            ->whereColumn('t.academic_year_id','c.academic_year_id')->exists();
        abort_unless($valid, 422, 'يجب أن يكون المعلم والمادة والشعبة مرتبطين بإسناد تعليمي صحيح.');
        $conflict = DB::table('class_schedule_entries')->where('school_id',$this->school())
            ->where('weekday',$data['weekday'])->where('starts_at','<',$data['ends_at'])
            ->where('ends_at','>',$data['starts_at'])
            ->where(function($q) use ($data) { $q->where('classroom_id',$data['classroom_id'])->orWhere('teacher_profile_id',$data['teacher_profile_id']); })
            ->when($ignoreId,fn($q)=>$q->where('id','!=',$ignoreId))->exists();
        if ($conflict) throw ValidationException::withMessages(['starts_at'=>['يوجد تعارض زمني مع حصة أخرى للمعلم أو الشعبة في اليوم نفسه.']]);
        return $data;
    }

    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['school_admin','super_admin'], true), 403);
        $data = $this->validateEntry($request);
        $id = DB::table('class_schedule_entries')->insertGetId($data + ['school_id'=>$this->school(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'تمت إضافة الحصة إلى الجدول الأسبوعي.','entry'=>$this->scheduleQuery()->where('se.id',$id)->first()],201);
    }

    public function update(Request $request, int $id)
    {
        abort_unless(in_array($request->user()->role, ['school_admin','super_admin'], true), 403);
        abort_unless(DB::table('class_schedule_entries')->where('id',$id)->where('school_id',$this->school())->exists(),404);
        $data = $this->validateEntry($request, $id);
        DB::table('class_schedule_entries')->where('id',$id)->update($data+['updated_at'=>now()]);
        return response()->json(['message'=>'تم تحديث الحصة.','entry'=>$this->scheduleQuery()->where('se.id',$id)->first()]);
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless(in_array($request->user()->role, ['school_admin','super_admin'], true), 403);
        $deleted = DB::table('class_schedule_entries')->where('id',$id)->where('school_id',$this->school())->delete();
        abort_unless($deleted,404);
        return response()->json(['message'=>'تم حذف الحصة من الجدول.']);
    }

    public function teacherSchedule(Request $request)
    {
        abort_unless($request->user()->role === 'teacher',403);
        $teacherId = DB::table('teacher_profiles')->where('school_id',$this->school())->where('user_id',$request->user()->id)->value('id');
        abort_unless($teacherId,403,'لا يوجد ملف معلم مرتبط بهذا الحساب.');
        return response()->json(['entries'=>$this->scheduleQuery()->where('se.teacher_profile_id',$teacherId)->orderBy('se.weekday')->orderBy('se.starts_at')->get()]);
    }

    public function studentSchedule(Request $request)
    {
        abort_unless($request->user()->role === 'student',403);
        $studentId = DB::table('student_profiles')->where('school_id',$this->school())->where('user_id',$request->user()->id)->value('id');
        abort_unless($studentId,403,'لا يوجد ملف طالب مرتبط بهذا الحساب.');
        $classroomIds = DB::table('student_enrollments')->where('school_id',$this->school())->where('student_profile_id',$studentId)->where('status','active')->pluck('classroom_id');
        $entries = $this->scheduleQuery()->whereIn('se.classroom_id',$classroomIds)->orderBy('se.weekday')->orderBy('se.starts_at')->get();
        return response()->json(['entries'=>$entries]);
    }
}

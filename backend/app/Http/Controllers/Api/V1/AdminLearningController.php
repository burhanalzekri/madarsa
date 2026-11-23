<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminLearningController extends Controller
{
    private function school(): int { return (int) CurrentSchool::id(); }

    public function index(Request $request)
    {
        $school = $this->school();
        $structure = DB::table('grade_subjects as gs')
            ->join('grades as g','g.id','=','gs.grade_id')->join('subjects as s','s.id','=','gs.subject_id')
            ->join('terms as t','t.id','=','gs.term_id')->join('academic_years as ay','ay.id','=','t.academic_year_id')
            ->where('g.school_id',$school)->where('ay.school_id',$school)
            ->select('gs.id','gs.grade_id','g.name as grade','s.name as subject','t.name as term','ay.name as academic_year')
            ->orderBy('g.name')->orderBy('s.name')->get();
        $classrooms = DB::table('classrooms as c')->join('grades as g','g.id','=','c.grade_id')
            ->where('c.school_id',$school)->where('c.is_active',true)
            ->select('c.id','c.grade_id','c.name','g.name as grade')->orderBy('g.name')->orderBy('c.name')->get();
        $teachers = DB::table('teacher_profiles as tp')->join('users as u','u.id','=','tp.user_id')
            ->where('tp.school_id',$school)->where('tp.status','active')->select('tp.id','u.name')->orderBy('u.name')->get();

        $students = DB::table('student_enrollments as e')->join('student_profiles as sp','sp.id','=','e.student_profile_id')
            ->join('users as u','u.id','=','sp.user_id')->join('classrooms as c','c.id','=','e.classroom_id')
            ->join('grades as g','g.id','=','c.grade_id')->where('e.school_id',$school)->where('e.status','active')
            ->select('sp.id as student_id','sp.student_number','u.name','c.id as classroom_id','c.name as classroom','g.name as grade')
            ->orderBy('u.name')->limit(300)->get()->map(function($student) use ($school) {
                $gradeSubjectIds = DB::table('grade_subjects')->where('grade_id',DB::table('classrooms')->where('id',$student->classroom_id)->value('grade_id'))->pluck('id');
                $lessonIds = DB::table('lessons as l')->join('curriculum_units as cu','cu.id','=','l.curriculum_unit_id')
                    ->whereIn('cu.grade_subject_id',$gradeSubjectIds)->where('l.is_published',true)->pluck('l.id');
                $lessonsTotal = $lessonIds->count();
                $lessonsDone = $lessonsTotal ? DB::table('lesson_progress')->where('school_id',$school)->where('student_profile_id',$student->student_id)->whereIn('lesson_id',$lessonIds)->where('completion_percent',100)->count() : 0;
                $assignmentIds = DB::table('assignments')->where('school_id',$school)->where('classroom_id',$student->classroom_id)->where('is_published',true)->pluck('id');
                $assignmentsDone = $assignmentIds->count() ? DB::table('assignment_submissions')->where('student_profile_id',$student->student_id)->whereIn('assignment_id',$assignmentIds)->whereNotNull('submitted_at')->count() : 0;
                $examIds = DB::table('exams')->where('school_id',$school)->where('classroom_id',$student->classroom_id)->where('is_published',true)->pluck('id');
                $examsDone = $examIds->count() ? DB::table('exam_attempts')->where('student_profile_id',$student->student_id)->whereIn('exam_id',$examIds)->whereIn('status',['submitted','submitted_for_review','graded'])->count() : 0;
                $total = $lessonsTotal + $assignmentIds->count() + $examIds->count();
                $done = $lessonsDone + $assignmentsDone + $examsDone;
                return ['student_id'=>$student->student_id,'student_number'=>$student->student_number,'name'=>$student->name,
                    'grade'=>$student->grade,'classroom'=>$student->classroom,'lessons_total'=>$lessonsTotal,'lessons_done'=>$lessonsDone,
                    'assignments_total'=>$assignmentIds->count(),'assignments_done'=>$assignmentsDone,'exams_total'=>$examIds->count(),
                    'exams_done'=>$examsDone,'completion_percent'=>$total ? (int) round($done/$total*100) : 0];
            });

        return response()->json([
            'grade_subjects'=>$structure,'classrooms'=>$classrooms,'teachers'=>$teachers,'students'=>$students,
            'assignments'=>DB::table('assignments as a')->join('classrooms as c','c.id','=','a.classroom_id')->join('grade_subjects as gs','gs.id','=','a.grade_subject_id')->join('subjects as s','s.id','=','gs.subject_id')
                ->where('a.school_id',$school)->select('a.id','a.title','a.is_published','a.due_at','a.max_score','c.name as classroom','s.name as subject')->orderByDesc('a.id')->limit(100)->get(),
            'exams'=>DB::table('exams as e')->join('classrooms as c','c.id','=','e.classroom_id')
                ->where('e.school_id',$school)->select('e.id','e.title','e.is_published','e.starts_at','e.duration_minutes','e.total_score','c.name as classroom')->orderByDesc('e.id')->limit(100)->get(),
        ]);
    }

    private function validateTeachingScope(array $data): void
    {
        $school = $this->school();
        $subject = DB::table('grade_subjects as gs')->join('grades as g','g.id','=','gs.grade_id')->join('terms as t','t.id','=','gs.term_id')
            ->where('gs.id',$data['grade_subject_id'])->where('g.school_id',$school)->select('gs.id','gs.grade_id','t.academic_year_id')->first();
        $classroom = DB::table('classrooms')->where('id',$data['classroom_id'])->where('school_id',$school)->first();
        $teacherExists = DB::table('teacher_profiles')->where('id',$data['teacher_profile_id'])->where('school_id',$school)->where('status','active')->exists();
        $assigned = DB::table('teacher_assignments')->where('school_id',$school)->where('teacher_profile_id',$data['teacher_profile_id'])
            ->where('grade_subject_id',$data['grade_subject_id'])->where('academic_year_id',$classroom->academic_year_id ?? 0)->where(function($q) use($data){$q->whereNull('classroom_id')->orWhere('classroom_id',$data['classroom_id']);})->exists();
        abort_unless($subject && $classroom && (int)$subject->grade_id === (int)$classroom->grade_id && (int)$subject->academic_year_id === (int)$classroom->academic_year_id && $teacherExists && $assigned,
            422,'تحقق من المادة والشعبة والمعلم؛ يجب أن تكون ضمن الصف نفسه وأن تكون المادة مسندة للمعلم.');
    }

    public function createAssignment(Request $request)
    {
        $data = $request->validate(['teacher_profile_id'=>'required|integer','grade_subject_id'=>'required|integer','classroom_id'=>'required|integer',
            'title'=>'required|string|max:255','instructions'=>'nullable|string|max:20000','due_at'=>'nullable|date','max_score'=>'required|numeric|min:0.01|max:100000','is_published'=>'sometimes|boolean']);
        $this->validateTeachingScope($data);
        $id = DB::table('assignments')->insertGetId($data + ['school_id'=>$this->school(),'is_published'=>$data['is_published']??false,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'تم إنشاء الواجب وحفظه.','assignment'=>DB::table('assignments')->find($id)],201);
    }

    public function createExam(Request $request)
    {
        $data = $request->validate(['teacher_profile_id'=>'required|integer','grade_subject_id'=>'required|integer','classroom_id'=>'required|integer',
            'title'=>'required|string|max:255','instructions'=>'nullable|string|max:10000','starts_at'=>'nullable|date','duration_minutes'=>'required|integer|min:1|max:360',
            'total_score'=>'required|numeric|min:1|max:10000','is_published'=>'sometimes|boolean','questions'=>'required|array|min:1|max:60',
            'questions.*.question_type'=>'required|in:multiple_choice,true_false,essay','questions.*.question_text'=>'required|string|max:10000',
            'questions.*.options'=>'nullable|array|max:10','questions.*.options.*'=>'string|max:500','questions.*.correct_answer'=>'nullable|string|max:1000','questions.*.score'=>'required|numeric|min:0.01|max:10000']);
        $this->validateTeachingScope($data);
        $sum = collect($data['questions'])->sum(fn($q)=>(float)$q['score']);
        if ($sum > (float)$data['total_score']) throw ValidationException::withMessages(['questions'=>['مجموع درجات الأسئلة يتجاوز الدرجة النهائية.']]);
        foreach ($data['questions'] as $i=>$q) {
            if ($q['question_type']==='multiple_choice' && (empty($q['options']) || empty($q['correct_answer']) || !in_array($q['correct_answer'],$q['options'],true))) throw ValidationException::withMessages(["questions.$i.correct_answer"=>['حدد إجابة صحيحة من الخيارات.']]);
            if ($q['question_type']==='true_false' && !in_array($q['correct_answer']??'', ['true','false'],true)) throw ValidationException::withMessages(["questions.$i.correct_answer"=>['حدد صح أو خطأ كإجابة صحيحة.']]);
        }
        $id = DB::transaction(function() use($data) {
            $examId = DB::table('exams')->insertGetId(['school_id'=>$this->school(),'teacher_profile_id'=>$data['teacher_profile_id'],'grade_subject_id'=>$data['grade_subject_id'],
                'classroom_id'=>$data['classroom_id'],'title'=>$data['title'],'instructions'=>$data['instructions']??null,'starts_at'=>$data['starts_at']??null,
                'duration_minutes'=>$data['duration_minutes'],'total_score'=>$data['total_score'],'is_published'=>$data['is_published']??false,'created_at'=>now(),'updated_at'=>now()]);
            foreach($data['questions'] as $i=>$q) DB::table('exam_questions')->insert(['exam_id'=>$examId,'question_type'=>$q['question_type'],'question_text'=>$q['question_text'],
                'options'=>isset($q['options'])?json_encode($q['options'],JSON_UNESCAPED_UNICODE):null,'correct_answer'=>$q['question_type']==='essay'?null:($q['correct_answer']??null),
                'score'=>$q['score'],'sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]);
            return $examId;
        });
        return response()->json(['message'=>'تم إنشاء الاختبار وحفظ أسئلته.','exam'=>DB::table('exams')->find($id)],201);
    }
}

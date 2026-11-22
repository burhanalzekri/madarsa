<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    private function teacherId(Request $request): int
    {
        abort_unless($request->user()->role === 'teacher', 403, 'هذه الخدمة مخصصة للمعلم.');
        $id = DB::table('teacher_profiles')->where('school_id', CurrentSchool::id())
            ->where('user_id', $request->user()->id)->value('id');
        abort_unless($id, 403, 'لا يوجد ملف معلم مرتبط بهذا الحساب.');
        return (int) $id;
    }

    private function ownsClassroom(int $teacherId, int $classroomId, int $gradeSubjectId): bool
    {
        return DB::table('teacher_assignments')->where('school_id', CurrentSchool::id())
            ->where('teacher_profile_id', $teacherId)->where('grade_subject_id', $gradeSubjectId)
            ->where(function ($q) use ($classroomId) { $q->whereNull('classroom_id')->orWhere('classroom_id', $classroomId); })
            ->exists();
    }

    private function studentId(Request $request): int
    {
        $id = DB::table('student_profiles')->where('school_id', CurrentSchool::id())
            ->where('user_id', $request->user()->id)->value('id');
        abort_unless($id, 403, 'هذا الحساب غير مرتبط بملف طالب.');
        return (int) $id;
    }

    public function teacherExams(Request $request)
    {
        $teacherId = $this->teacherId($request);
        return DB::table('exams')->where('school_id', CurrentSchool::id())
            ->where('teacher_profile_id', $teacherId)->orderByDesc('created_at')->paginate(30);
    }

    public function createExam(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate([
            'grade_subject_id' => ['required','integer'], 'classroom_id' => ['required','integer'],
            'title' => ['required','string','max:255'], 'instructions' => ['nullable','string','max:10000'],
            'starts_at' => ['nullable','date'], 'duration_minutes' => ['required','integer','min:1','max:360'],
            'total_score' => ['required','numeric','min:1','max:10000'], 'is_published' => ['sometimes','boolean'],
            'questions' => ['required','array','min:1','max:60'],
            'questions.*.question_type' => ['required','in:multiple_choice,true_false,essay'],
            'questions.*.question_text' => ['required','string','max:10000'],
            'questions.*.options' => ['nullable','array','max:10'], 'questions.*.options.*' => ['string','max:500'],
            'questions.*.correct_answer' => ['nullable','string','max:1000'],
            'questions.*.score' => ['required','numeric','min:0.01','max:10000'],
        ]);
        abort_unless($this->ownsClassroom($teacherId, $data['classroom_id'], $data['grade_subject_id']), 403, 'المادة أو الشعبة غير مسندة إليك.');
        $sum = collect($data['questions'])->sum(fn ($q) => (float) $q['score']);
        if ($sum > (float) $data['total_score']) {
            throw ValidationException::withMessages(['questions' => ['مجموع درجات الأسئلة يتجاوز الدرجة النهائية للاختبار.']]);
        }
        foreach ($data['questions'] as $index => $question) {
            if ($question['question_type'] === 'multiple_choice' && (empty($question['options']) || empty($question['correct_answer']) || !in_array($question['correct_answer'], $question['options'], true))) {
                throw ValidationException::withMessages(["questions.$index.correct_answer" => ['يلزم تحديد خيارات وإجابة صحيحة موجودة ضمن الخيارات.']]);
            }
            if ($question['question_type'] === 'true_false' && !in_array($question['correct_answer'] ?? '', ['true','false'], true)) {
                throw ValidationException::withMessages(["questions.$index.correct_answer" => ['حدد الإجابة الصحيحة: true أو false.']]);
            }
        }
        $exam = DB::transaction(function () use ($data, $teacherId) {
            $examId = DB::table('exams')->insertGetId([
                'school_id'=>CurrentSchool::id(), 'teacher_profile_id'=>$teacherId,
                'grade_subject_id'=>$data['grade_subject_id'], 'classroom_id'=>$data['classroom_id'],
                'title'=>$data['title'], 'instructions'=>$data['instructions'] ?? null,
                'starts_at'=>$data['starts_at'] ?? null, 'duration_minutes'=>$data['duration_minutes'],
                'total_score'=>$data['total_score'], 'is_published'=>$data['is_published'] ?? false,
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            foreach ($data['questions'] as $index => $question) {
                DB::table('exam_questions')->insert([
                    'exam_id'=>$examId, 'question_type'=>$question['question_type'],
                    'question_text'=>$question['question_text'], 'options'=>isset($question['options']) ? json_encode($question['options'], JSON_UNESCAPED_UNICODE) : null,
                    'correct_answer'=>$question['question_type'] === 'essay' ? null : ($question['correct_answer'] ?? null),
                    'score'=>$question['score'], 'sort_order'=>$index, 'created_at'=>now(), 'updated_at'=>now(),
                ]);
            }
            return DB::table('exams')->find($examId);
        });
        return response()->json(['message'=>'تم إنشاء الاختبار وحفظ أسئلته.','exam'=>$exam], 201);
    }

    public function attendanceRoster(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate(['classroom_id'=>['required','integer'],'date'=>['nullable','date']]);
        abort_unless(DB::table('teacher_assignments')->where('school_id',CurrentSchool::id())
            ->where('teacher_profile_id',$teacherId)->where('classroom_id',$data['classroom_id'])->exists(), 403, 'الشعبة غير مسندة إليك.');
        $date = $data['date'] ?? now()->toDateString();
        return response()->json(['date'=>$date,'students'=>DB::table('student_enrollments as e')
            ->join('student_profiles as sp','sp.id','=','e.student_profile_id')->join('users as u','u.id','=','sp.user_id')
            ->leftJoin('attendance_records as ar',function($join) use($date){$join->on('ar.student_profile_id','=','sp.id')->where('ar.attendance_date','=',$date);})
            ->where('e.school_id',CurrentSchool::id())->where('e.classroom_id',$data['classroom_id'])->where('e.status','active')
            ->select('sp.id as student_profile_id','sp.student_number','u.name','ar.status','ar.notes')->orderBy('u.name')->get()]);
    }

    public function attendanceReport(Request $request)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate(['classroom_id'=>['required','integer'],'from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']]);
        abort_unless(DB::table('teacher_assignments')->where('school_id',CurrentSchool::id())->where('teacher_profile_id',$teacherId)->where('classroom_id',$data['classroom_id'])->exists(),403);
        $query = DB::table('attendance_records as ar')->join('student_profiles as sp','sp.id','=','ar.student_profile_id')
            ->join('users as u','u.id','=','sp.user_id')->where('ar.school_id',CurrentSchool::id())->where('ar.classroom_id',$data['classroom_id']);
        if (!empty($data['from'])) $query->whereDate('ar.attendance_date','>=',$data['from']);
        if (!empty($data['to'])) $query->whereDate('ar.attendance_date','<=',$data['to']);
        return response()->json(['records'=>$query->select('ar.attendance_date','ar.status','ar.notes','sp.student_number','u.name')->orderByDesc('ar.attendance_date')->limit(500)->get()]);
    }

    public function studentExam(Request $request, int $examId)
    {
        $studentId = $this->studentId($request);
        $exam = DB::table('exams')->where('id',$examId)->where('school_id',CurrentSchool::id())->where('is_published',true)->first();
        abort_unless($exam,404);
        abort_unless(DB::table('student_enrollments')->where('school_id',CurrentSchool::id())->where('student_profile_id',$studentId)->where('classroom_id',$exam->classroom_id)->where('status','active')->exists(),403,'هذا الاختبار غير مخصص لشعبتك.');
        return response()->json(['exam'=>$exam,'questions'=>DB::table('exam_questions')->where('exam_id',$examId)->orderBy('sort_order')->get()->map(function($q){
            return ['id'=>$q->id,'question_type'=>$q->question_type,'question_text'=>$q->question_text,'options'=>$q->options ? json_decode($q->options,true) : null,'score'=>$q->score];
        })]);
    }

    public function startAttempt(Request $request, int $examId)
    {
        $studentId = $this->studentId($request);
        $exam = DB::table('exams')->where('id',$examId)->where('school_id',CurrentSchool::id())->where('is_published',true)->first();
        abort_unless($exam,404);
        abort_unless(DB::table('student_enrollments')->where('school_id',CurrentSchool::id())->where('student_profile_id',$studentId)->where('classroom_id',$exam->classroom_id)->where('status','active')->exists(),403);
        if ($exam->starts_at && now()->lt($exam->starts_at)) abort(403,'لم يبدأ موعد الاختبار بعد.');
        $attempt = DB::table('exam_attempts')->where('exam_id',$examId)->where('student_profile_id',$studentId)->first();
        if ($attempt && $attempt->status !== 'in_progress') abort(409,'سبق تسليم هذا الاختبار.');
        if (!$attempt) {
            $id = DB::table('exam_attempts')->insertGetId(['exam_id'=>$examId,'student_profile_id'=>$studentId,'answers'=>'{}','started_at'=>now(),'status'=>'in_progress','created_at'=>now(),'updated_at'=>now()]);
            $attempt = DB::table('exam_attempts')->find($id);
        }
        return response()->json(['attempt'=>$attempt,'duration_minutes'=>$exam->duration_minutes]);
    }

    public function submitAttempt(Request $request, int $examId)
    {
        $studentId = $this->studentId($request);
        $data = $request->validate(['answers'=>['required','array','max:60'],'answers.*.question_id'=>['required','integer','distinct'],'answers.*.answer'=>['nullable','string','max:10000']]);
        $exam = DB::table('exams')->where('id',$examId)->where('school_id',CurrentSchool::id())->where('is_published',true)->first();
        abort_unless($exam,404);
        $attempt = DB::table('exam_attempts')->where('exam_id',$examId)->where('student_profile_id',$studentId)->where('status','in_progress')->first();
        abort_unless($attempt,409,'ابدأ الاختبار أولاً أو تم تسليمه سابقاً.');
        if (now()->gt($attempt->started_at ? \Illuminate\Support\Carbon::parse($attempt->started_at)->addMinutes($exam->duration_minutes + 2) : now()->addDay())) abort(422,'انتهى الوقت المخصص للاختبار.');
        $questions = DB::table('exam_questions')->where('exam_id',$examId)->get()->keyBy('id');
        $answers = []; $score = 0.0; $hasEssay = false;
        foreach ($data['answers'] as $item) {
            $question = $questions->get($item['question_id']);
            abort_unless($question,422,'تتضمن الإجابات سؤالاً لا يتبع هذا الاختبار.');
            $answer = trim((string)($item['answer'] ?? ''));
            $answers[$question->id] = $answer;
            if ($question->question_type === 'essay') { $hasEssay = true; continue; }
            if ($answer !== '' && hash_equals((string)$question->correct_answer, $answer)) $score += (float)$question->score;
        }
        DB::table('exam_attempts')->where('id',$attempt->id)->update([
            'answers'=>json_encode($answers,JSON_UNESCAPED_UNICODE),'score'=>$score,
            'submitted_at'=>now(),'status'=>$hasEssay?'submitted_for_review':'submitted','updated_at'=>now(),
        ]);
        return response()->json(['message'=>$hasEssay?'تم تسليم الاختبار، وتحتاج الإجابات المقالية إلى تصحيح المعلم.':'تم تسليم الاختبار وتصحيحه تلقائياً.','score'=>$hasEssay?null:$score,'status'=>$hasEssay?'submitted_for_review':'submitted']);
    }

    public function examAttempts(Request $request, int $examId)
    {
        $teacherId = $this->teacherId($request);
        $exam = DB::table('exams')->where('id',$examId)->where('school_id',CurrentSchool::id())->where('teacher_profile_id',$teacherId)->first();
        abort_unless($exam,404);
        $questions = DB::table('exam_questions')->where('exam_id',$examId)->orderBy('sort_order')->get();
        $attempts = DB::table('exam_attempts as a')->join('student_profiles as sp','sp.id','=','a.student_profile_id')->join('users as u','u.id','=','sp.user_id')
            ->where('a.exam_id',$examId)->select('a.id','a.score','a.status','a.started_at','a.submitted_at','a.answers','sp.student_number','u.name')->orderByDesc('a.submitted_at')->get()
            ->map(function ($attempt) use ($questions) {
                $answers = $attempt->answers ? json_decode($attempt->answers, true) : [];
                return [
                    'id'=>$attempt->id,'score'=>$attempt->score,'status'=>$attempt->status,
                    'started_at'=>$attempt->started_at,'submitted_at'=>$attempt->submitted_at,
                    'student_number'=>$attempt->student_number,'name'=>$attempt->name,
                    'answers'=>$questions->map(fn($q)=>['question_text'=>$q->question_text,'question_type'=>$q->question_type,'answer'=>$answers[$q->id] ?? '','score'=>$q->score])->values(),
                ];
            });
        return response()->json(['attempts'=>$attempts]);
    }

    public function gradeAttempt(Request $request, int $attemptId)
    {
        $teacherId = $this->teacherId($request);
        $data = $request->validate(['score'=>['required','numeric','min:0'],'teacher_feedback'=>['nullable','string','max:5000']]);
        $attempt = DB::table('exam_attempts as a')->join('exams as e','e.id','=','a.exam_id')
            ->where('a.id',$attemptId)->where('e.school_id',CurrentSchool::id())->where('e.teacher_profile_id',$teacherId)
            ->select('a.id','a.status','e.total_score')->first();
        abort_unless($attempt,404);
        abort_if($attempt->status === 'in_progress', 422, 'لا يمكن تصحيح محاولة لم يتم تسليمها بعد.');
        if ((float)$data['score'] > (float)$attempt->total_score) throw ValidationException::withMessages(['score'=>['الدرجة تتجاوز الدرجة النهائية للاختبار.']]);
        DB::table('exam_attempts')->where('id',$attemptId)->update(['score'=>$data['score'],'status'=>'graded','updated_at'=>now()]);
        return response()->json(['message'=>'تم اعتماد درجة الاختبار.']);
    }
}

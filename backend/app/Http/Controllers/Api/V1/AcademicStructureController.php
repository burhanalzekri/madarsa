<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{AcademicStage, Grade, AcademicYear, Term, Subject, GradeSubject, Classroom, TeacherProfile};
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicStructureController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['school_admin', 'super_admin'], true), 403, 'ليست لديك صلاحية إدارة الهيكل الأكاديمي.');
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);
        $school = CurrentSchool::id();
        return response()->json([
            'stages' => AcademicStage::where('school_id', $school)->withCount('grades')->orderBy('sort_order')->orderBy('id')->get(),
            'grades' => Grade::where('school_id', $school)->with('stage:id,name')->withCount('classrooms')->orderBy('sort_order')->orderBy('id')->get(),
            'years' => AcademicYear::where('school_id', $school)->withCount('terms')->orderByDesc('is_current')->orderByDesc('id')->get(),
            'terms' => Term::whereHas('academicYear', fn ($q) => $q->where('school_id', $school))->with('academicYear:id,name')->orderBy('term_number')->get(),
            'subjects' => Subject::where('school_id', $school)->withCount('gradeSubjects')->orderBy('name')->get(),
            'grade_subjects' => GradeSubject::whereHas('grade', fn ($q) => $q->where('school_id', $school))->with(['grade:id,name', 'term:id,name,academic_year_id', 'term.academicYear:id,name', 'subject:id,name'])->orderByDesc('id')->get(),
            'classrooms' => Classroom::where('school_id', $school)->with(['grade:id,name', 'academicYear:id,name'])->withCount(['studentEnrollments as students_count' => fn ($q) => $q->where('status', 'active')])->orderBy('grade_id')->orderBy('name')->get(),
            'teachers' => TeacherProfile::where('school_id', $school)->where('status', 'active')->with('user:id,name')->orderBy('id')->get(),
            'assignments' => \App\Models\TeacherAssignment::where('school_id',$school)->with(['teacher.user:id,name','gradeSubject.grade:id,name','gradeSubject.subject:id,name','gradeSubject.term:id,name','classroom:id,name'])->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->authorizeManager($request);
        $school = CurrentSchool::id();
        $data = match ($type) {
            'stages' => $request->validate(['name'=>'required|string|max:120', 'sort_order'=>'nullable|integer|min:0|max:999', 'is_active'=>'sometimes|boolean']),
            'grades' => $request->validate(['academic_stage_id'=>['required','integer',Rule::exists('academic_stages','id')->where('school_id',$school)], 'name'=>'required|string|max:120', 'code'=>'nullable|string|max:30', 'sort_order'=>'nullable|integer|min:0|max:999', 'is_active'=>'sometimes|boolean']),
            'years' => $request->validate(['name'=>'required|string|max:40', 'starts_at'=>'nullable|date', 'ends_at'=>'nullable|date|after_or_equal:starts_at', 'is_current'=>'sometimes|boolean']),
            'terms' => $request->validate(['academic_year_id'=>['required','integer',Rule::exists('academic_years','id')->where('school_id',$school)], 'name'=>'required|string|max:100', 'term_number'=>'required|integer|min:1|max:4', 'is_active'=>'sometimes|boolean']),
            'subjects' => $request->validate(['name'=>'required|string|max:120', 'subject_type'=>'nullable|string|max:40', 'language_code'=>'nullable|string|max:8', 'is_active'=>'sometimes|boolean']),
            'grade-subjects' => $request->validate(['grade_id'=>['required','integer',Rule::exists('grades','id')->where('school_id',$school)], 'term_id'=>'required|integer|exists:terms,id', 'subject_id'=>['required','integer',Rule::exists('subjects','id')->where('school_id',$school)], 'weekly_lessons'=>'nullable|integer|min:1|max:40']),
            default => abort(404, 'نوع السجل غير معروف.'),
        };

        if ($type === 'grade-subjects') { abort_unless(Term::whereHas('academicYear', fn($q) => $q->where('school_id',$school))->whereKey($data['term_id'])->exists(), 422, 'الفصل الدراسي لا يتبع هذه المدرسة.'); }

        $model = DB::transaction(function () use ($type, $data, $school) {
            return match ($type) {
                'stages' => AcademicStage::create($data + ['school_id'=>$school]),
                'grades' => Grade::create($data + ['school_id'=>$school]),
                'years' => $this->createYear($data, $school),
                'terms' => Term::create($data),
                'subjects' => Subject::create($data + ['school_id'=>$school]),
                'grade-subjects' => GradeSubject::firstOrCreate(['grade_id'=>$data['grade_id'], 'term_id'=>$data['term_id'], 'subject_id'=>$data['subject_id']], ['weekly_lessons'=>$data['weekly_lessons'] ?? null]),
            };
        });
        return response()->json(['message'=>'تم حفظ البيانات الأكاديمية بنجاح.', 'record'=>$model], 201);
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->authorizeManager($request);
        $school = CurrentSchool::id();
        [$model, $rules] = match ($type) {
            'stages' => [AcademicStage::where('school_id',$school)->findOrFail($id), ['name'=>'sometimes|required|string|max:120','sort_order'=>'sometimes|integer|min:0|max:999','is_active'=>'sometimes|boolean']],
            'grades' => [Grade::where('school_id',$school)->findOrFail($id), ['academic_stage_id'=>['sometimes','required','integer',Rule::exists('academic_stages','id')->where('school_id',$school)],'name'=>'sometimes|required|string|max:120','code'=>'nullable|string|max:30','sort_order'=>'sometimes|integer|min:0|max:999','is_active'=>'sometimes|boolean']],
            'years' => [AcademicYear::where('school_id',$school)->findOrFail($id), ['name'=>'sometimes|required|string|max:40','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','is_current'=>'sometimes|boolean']],
            'terms' => [Term::whereHas('academicYear',fn($q)=>$q->where('school_id',$school))->findOrFail($id), ['academic_year_id'=>['sometimes','required','integer',Rule::exists('academic_years','id')->where('school_id',$school)],'name'=>'sometimes|required|string|max:100','term_number'=>'sometimes|required|integer|min:1|max:4','is_active'=>'sometimes|boolean']],
            'subjects' => [Subject::where('school_id',$school)->findOrFail($id), ['name'=>'sometimes|required|string|max:120','subject_type'=>'nullable|string|max:40','language_code'=>'nullable|string|max:8','is_active'=>'sometimes|boolean']],
            'grade-subjects' => [GradeSubject::whereHas('grade',fn($q)=>$q->where('school_id',$school))->findOrFail($id), ['weekly_lessons'=>'nullable|integer|min:1|max:40']],
            default => abort(404, 'نوع السجل غير معروف.'),
        };
        $data = $request->validate($rules);
        if ($type === 'years' && ($data['is_current'] ?? false)) {
            DB::transaction(function () use ($model, $data, $school) { AcademicYear::where('school_id',$school)->where('id', '!=', $model->id)->update(['is_current'=>false]); $model->update($data); });
        } else { $model->update($data); }
        return response()->json(['message'=>'تم تحديث البيانات الأكاديمية.', 'record'=>$model->fresh()]);
    }

    private function createYear(array $data, int $school): AcademicYear
    {
        if ($data['is_current'] ?? false) AcademicYear::where('school_id',$school)->update(['is_current'=>false]);
        return AcademicYear::create($data + ['school_id'=>$school]);
    }
}

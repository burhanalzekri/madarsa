<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\{TeacherProfile,GradeSubject,Classroom,AcademicYear,TeacherAssignment};
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
class TeacherAssignmentController extends Controller {
 private function manager(Request $r):void{abort_unless(in_array($r->user()->role,['school_admin','super_admin'],true),403,'ليست لديك صلاحية إسناد المواد.');}
 public function index(Request $r){$this->manager($r);return TeacherAssignment::with(['teacher.user','gradeSubject.subject','gradeSubject.grade','classroom'])->where('school_id',CurrentSchool::id())->when($r->filled('academic_year_id'),fn($q)=>$q->where('academic_year_id',$r->integer('academic_year_id')))->paginate(50);}
 public function store(Request $r){$this->manager($r);$d=$r->validate(['teacher_profile_id'=>'required|integer|exists:teacher_profiles,id','grade_subject_id'=>'required|integer|exists:grade_subjects,id','classroom_id'=>'nullable|integer|exists:classrooms,id','academic_year_id'=>'required|integer|exists:academic_years,id']);$school=CurrentSchool::id();TeacherProfile::where('school_id',$school)->findOrFail($d['teacher_profile_id']);$gs=GradeSubject::with('grade')->findOrFail($d['grade_subject_id']);abort_unless($gs->grade && $gs->grade->school_id===$school,404);$year=AcademicYear::where('school_id',$school)->findOrFail($d['academic_year_id']);abort_unless((int)$gs->term->academic_year_id===(int)$year->id,422,'المادة والفصل الدراسي لا يتبعان السنة المحددة.');if(!empty($d['classroom_id']))Classroom::where('school_id',$school)->where('grade_id',$gs->grade_id)->where('academic_year_id',$year->id)->findOrFail($d['classroom_id']);$a=TeacherAssignment::firstOrCreate(['teacher_profile_id'=>$d['teacher_profile_id'],'grade_subject_id'=>$d['grade_subject_id'],'classroom_id'=>$d['classroom_id']??null,'academic_year_id'=>$d['academic_year_id']],['school_id'=>$school]);return response()->json(['message'=>'تم إسناد المادة للمعلم.','assignment'=>$a->load('teacher.user','gradeSubject.subject','classroom')],201);}
}

<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\{Classroom,Grade,AcademicYear};
use App\Support\CurrentSchool;
use Illuminate\Http\Request;
class ClassroomController extends Controller {
 private function manager(Request $r):void{abort_unless(in_array($r->user()->role,['school_admin','super_admin'],true),403,'ليست لديك صلاحية إدارة الشعب.');}
 public function index(Request $r){$this->manager($r);return Classroom::with(['grade','academicYear'])->where('school_id',CurrentSchool::id())->when($r->filled('grade_id'),fn($q)=>$q->where('grade_id',$r->integer('grade_id')))->when($r->filled('academic_year_id'),fn($q)=>$q->where('academic_year_id',$r->integer('academic_year_id')))->orderBy('grade_id')->orderBy('name')->paginate(50);}
 public function store(Request $r){$this->manager($r);$d=$r->validate(['grade_id'=>'required|integer|exists:grades,id','academic_year_id'=>'required|integer|exists:academic_years,id','name'=>'required|string|max:30','capacity'=>'nullable|integer|min:1|max:500','is_active'=>'sometimes|boolean']);$school=CurrentSchool::id();Grade::where('school_id',$school)->findOrFail($d['grade_id']);AcademicYear::where('school_id',$school)->findOrFail($d['academic_year_id']);$c=Classroom::create($d+['school_id'=>$school]);return response()->json(['message'=>'تم إنشاء الشعبة.','classroom'=>$c->load('grade','academicYear')],201);}
 public function update(Request $r,int $id){$this->manager($r);$c=Classroom::where('school_id',CurrentSchool::id())->findOrFail($id);$d=$r->validate(['name'=>'sometimes|required|string|max:30','capacity'=>'nullable|integer|min:1|max:500','is_active'=>'sometimes|boolean']);$c->update($d);return response()->json(['message'=>'تم تحديث الشعبة.','classroom'=>$c->fresh()]);}
}

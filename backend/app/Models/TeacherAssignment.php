<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TeacherAssignment extends Model {
 protected $fillable=['school_id','teacher_profile_id','grade_subject_id','classroom_id','academic_year_id'];
 public function teacher(): BelongsTo{return $this->belongsTo(TeacherProfile::class,'teacher_profile_id');}
 public function gradeSubject(): BelongsTo{return $this->belongsTo(GradeSubject::class);}
 public function classroom(): BelongsTo{return $this->belongsTo(Classroom::class);}
}

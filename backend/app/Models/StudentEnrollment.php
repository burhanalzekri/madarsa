<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StudentEnrollment extends Model {
 protected $fillable=['school_id','student_profile_id','classroom_id','academic_year_id','status','enrolled_at','ended_at'];
 protected $casts=['enrolled_at'=>'date','ended_at'=>'date'];
 public function student(): BelongsTo{return $this->belongsTo(StudentProfile::class,'student_profile_id');}
 public function classroom(): BelongsTo{return $this->belongsTo(Classroom::class);}
}

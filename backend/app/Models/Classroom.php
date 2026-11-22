<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Classroom extends Model {
 protected $fillable=['school_id','grade_id','academic_year_id','name','capacity','is_active'];
 protected $casts=['is_active'=>'boolean'];
 public function grade(): BelongsTo{return $this->belongsTo(Grade::class);}
 public function academicYear(): BelongsTo{return $this->belongsTo(AcademicYear::class);}
 public function studentEnrollments(): HasMany{return $this->hasMany(StudentEnrollment::class);}
}

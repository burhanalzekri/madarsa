<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StudentProfile extends Model {
 protected $fillable=['school_id','user_id','student_number','national_id','birth_date','gender','address','status','admission_date','notes'];
 protected $casts=['birth_date'=>'date','admission_date'=>'date'];
 public function user(): BelongsTo{return $this->belongsTo(User::class);}
 public function enrollments(): HasMany{return $this->hasMany(StudentEnrollment::class);}
}

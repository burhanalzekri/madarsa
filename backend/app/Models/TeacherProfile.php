<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TeacherProfile extends Model {
 protected $fillable=['school_id','user_id','employee_number','specialization','qualification','hire_date','phone','status','bio'];
 protected $casts=['hire_date'=>'date'];
 public function user(): BelongsTo{return $this->belongsTo(User::class);}
 public function assignments(): HasMany{return $this->hasMany(TeacherAssignment::class);}
}

<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
class User extends Authenticatable {
 use HasApiTokens, Notifiable;
 protected $fillable=['name','email','phone','password','role','is_active'];
 protected $hidden=['password','remember_token'];
 protected $casts=['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean'];
 public function studentProfile(): HasOne{return $this->hasOne(StudentProfile::class);}
 public function teacherProfile(): HasOne{return $this->hasOne(TeacherProfile::class);}
}

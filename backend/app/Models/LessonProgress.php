<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $fillable = ['school_id', 'student_profile_id', 'lesson_id', 'completion_percent', 'started_at', 'completed_at', 'last_viewed_at'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime', 'last_viewed_at' => 'datetime'];
    public function student(): BelongsTo { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
}

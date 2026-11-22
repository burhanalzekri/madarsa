<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumUnit extends Model
{
    protected $fillable = ['grade_subject_id', 'title', 'sort_order'];
    public function gradeSubject(): BelongsTo { return $this->belongsTo(GradeSubject::class); }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeSubject extends Model
{
    protected $fillable = ['grade_id', 'term_id', 'subject_id', 'weekly_lessons'];
    public function grade(): BelongsTo { return $this->belongsTo(Grade::class); }
    public function term(): BelongsTo { return $this->belongsTo(Term::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
}

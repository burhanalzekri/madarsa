<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['school_id', 'name', 'subject_type', 'language_code', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function gradeSubjects(): HasMany
    {
        return $this->hasMany(GradeSubject::class);
    }
}

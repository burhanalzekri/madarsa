<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = ['curriculum_unit_id', 'title', 'description', 'video_url', 'audio_url', 'attachment_path', 'sort_order', 'is_published'];
    protected $casts = ['is_published' => 'boolean'];
    public function unit(): BelongsTo { return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id'); }
    public function resources(): HasMany { return $this->hasMany(EducationalResource::class); }
    public function progress(): HasMany { return $this->hasMany(LessonProgress::class); }
}

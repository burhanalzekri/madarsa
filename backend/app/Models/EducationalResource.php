<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationalResource extends Model
{
    protected $fillable = ['school_id', 'lesson_id', 'uploaded_by', 'title', 'resource_type', 'disk', 'file_path', 'external_url', 'mime_type', 'file_size', 'is_published', 'metadata'];
    protected $casts = ['is_published' => 'boolean', 'metadata' => 'array'];
    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}

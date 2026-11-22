<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_announcements', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('created_by')->constrained('classrooms')->nullOnDelete();
            $table->index(['school_id', 'classroom_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('school_announcements', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'classroom_id', 'is_published']);
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};

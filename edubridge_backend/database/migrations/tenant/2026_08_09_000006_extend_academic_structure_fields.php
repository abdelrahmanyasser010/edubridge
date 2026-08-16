<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sections', function (Blueprint $table) {
            $table->string('room_number', 64)->nullable()->after('code');
            $table->foreignId('homeroom_teacher_id')->nullable()->after('capacity')->constrained('teachers')->nullOnDelete();
        });
        Schema::connection('tenant')->table('grade_level_subject', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_periods')->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('grade_level_subject', function (Blueprint $table) {
            $table->dropColumn('weekly_periods');
        });
        Schema::connection('tenant')->table('sections', function (Blueprint $table) {
            $table->dropForeign(['homeroom_teacher_id']);
            $table->dropColumn(['room_number', 'homeroom_teacher_id']);
        });
    }
};

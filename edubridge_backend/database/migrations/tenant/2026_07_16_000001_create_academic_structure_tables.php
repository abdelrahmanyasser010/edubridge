<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        Schema::connection('tenant')->create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 64)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 64)->unique();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 64);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['grade_level_id', 'code']);
        });

        Schema::connection('tenant')->create('grade_level_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['grade_level_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('grade_level_subject');
        Schema::connection('tenant')->dropIfExists('sections');
        Schema::connection('tenant')->dropIfExists('subjects');
        Schema::connection('tenant')->dropIfExists('grade_levels');
        Schema::connection('tenant')->dropIfExists('academic_terms');
        Schema::connection('tenant')->dropIfExists('academic_years');
    }
};

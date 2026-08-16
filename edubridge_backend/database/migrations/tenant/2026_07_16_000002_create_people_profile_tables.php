<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->nullable()->index();
            $table->string('employee_number', 64)->unique();
            $table->string('full_name', 160);
            $table->string('email', 160)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('parents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->nullable()->index();
            $table->string('full_name', 160);
            $table->string('email', 160)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('national_id_last4', 4)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->nullable()->index();
            $table->string('admission_number', 64)->unique();
            $table->string('full_name', 160);
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 32)->nullable();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('students');
        Schema::connection('tenant')->dropIfExists('parents');
        Schema::connection('tenant')->dropIfExists('teachers');
    }
};

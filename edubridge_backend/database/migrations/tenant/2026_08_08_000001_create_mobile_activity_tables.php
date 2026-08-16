<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('location', 180)->nullable();
            $table->string('organizer', 180)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->string('status', 32)->default('published')->index();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });

        Schema::connection('tenant')->create('activity_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_activity_id')->constrained('school_activities')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedBigInteger('registered_by_central_user_id')->index();
            $table->foreignId('finance_invoice_id')->nullable()->constrained('finance_invoices')->nullOnDelete();
            $table->string('status', 32)->default('confirmed')->index();
            $table->timestamp('registered_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['school_activity_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('activity_registrations');
        Schema::connection('tenant')->dropIfExists('school_activities');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('student_parent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->string('relationship', 64);
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('can_pickup')->default(false);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['student_id', 'parent_id']);
            $table->index(['student_id', 'status']);
            $table->index(['parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_parent');
    }
};

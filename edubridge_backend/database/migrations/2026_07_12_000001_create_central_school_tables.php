<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 8)->default('ar');
            $table->char('currency', 3)->default('SAR');
            $table->string('status', 32)->default('provisioning')->index();
            $table->timestamps();
        });

        Schema::create('school_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('host')->unique();
            $table->string('app_identifier')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'app_identifier']);
        });

        Schema::create('school_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_key', 64);
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id']);
        });

        Schema::create('tenant_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('driver', 32)->default('mysql');
            $table->string('database');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->string('secret_ref')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 32)->default('provisioning')->index();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 128);
            $table->string('subject_type', 128)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id', 128)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('tenant_connections');
        Schema::dropIfExists('school_user');
        Schema::dropIfExists('school_domains');
        Schema::dropIfExists('schools');
    }
};
